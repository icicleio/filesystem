<?php
namespace Icicle\File\Eio;

use Icicle\File\Exception\FileException;
use Icicle\File\FileInterface;
use Icicle\Promise\Promise;
use Icicle\Stream\Exception\InvalidArgumentError;
use Icicle\Stream\Exception\OutOfBoundsException;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnseekableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\PipeTrait;

class EioFile implements FileInterface
{
    use PipeTrait;

    /**
     * @var \Icicle\File\Eio\EioPoll
     */
    private $poll;

    /**
     * @var int
     */
    private $handle;

    /**
     * @var int
     */
    private $size = 0;

    /**
     * @var int
     */
    private $position = 0;

    /**
     * @var bool
     */
    private $append = false;

    /**
     * @var \SplQueue
     */
    private $queue;

    /**
     * @var bool
     */
    private $writable = true;

    /**
     * @param \Icicle\File\Eio\EioPoll $poll
     * @param int $handle
     * @param int $size
     * @param bool $append
     */
    public function __construct(EioPoll $poll, $handle, $size, $append = false)
    {
        $this->poll = $poll;
        $this->handle = $handle;
        $this->size = $size;
        $this->append = $append;
        $this->position = $append ? $size : 0;

        $this->queue = new \SplQueue();
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen()
    {
        return 0 !== $this->handle;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (0 !== $this->handle) {
            \eio_close($this->handle);
            $this->handle = 0;
        }

        $this->writable = false;

        while (!$this->queue->isEmpty()) {
            $promise = $this->queue->shift();
            $promise->cancel(new FileException('The file was closed.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        return $this->position === $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length = 0, $byte = null, $timeout = 0)
    {
        if (!$this->isReadable()) {
            throw new UnreadableException('The file is no longer readable.');
        }

        $length = (int) $length;
        if (0 >= $length) {
            $length = self::CHUNK_SIZE;
        }

        $remaining = $this->size - $this->position;
        $length = $length > $remaining ? $remaining : $length;

        $promise = new Promise(function (callable $resolve, callable $reject) use ($length) {
            $resource = \eio_read(
                $this->handle,
                $length,
                $this->position,
                null,
                function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Reading from file failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve($result);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('Could not initialize file read.');
            }
        });

        $this->poll->listen();

        if ($timeout) {
            $promise = $promise->timeout($timeout);
        }

        try {
            $data = (yield $promise);
        } finally {
            $this->poll->done();
        }

        $byte = (string) $byte;
        $byte = strlen($byte) ? $byte[0] : null;

        if (null !== $byte && false !== ($position = strpos($data, $byte))) {
            $data = substr($data, 0, $position + 1);
        }

        $this->position += strlen($data);

        yield $data;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return $this->isOpen() && !$this->eof();
    }

    /**
     * {@inheritdoc}
     */
    public function write($data, $timeout = 0)
    {
        return $this->send($data, $timeout, false);
    }

    /**
     * {@inheritdoc}
     */
    public function end($data = '', $timeout = 0)
    {
        return $this->send($data, $timeout, true);
    }

    /**
     * @coroutine
     *
     * @param string $data
     * @param bool $end
     *
     * @return \Generator
     *
     * @throws \Icicle\Stream\Exception\UnwritableException
     */
    protected function send($data, $timeout, $end = false)
    {
        if (!$this->isWritable()) {
            throw new UnwritableException('The file is no longer writable.');
        }

        $data = (string) $data;

        if ($this->queue->isEmpty()) {
            $promise = $this->push($data);
        } else {
            $promise = $this->queue->top();
            $promise = $promise->then(function () use ($data) {
                return $this->push($data);
            });
        }

        $this->queue->push($promise);

        if ($end) {
            $this->writable = false;
        }

        if ($timeout) {
            $promise = $promise->timeout($timeout);
        }

        $this->poll->listen();

        try {
            yield $promise;
        } finally {
            if ($end) {
                $this->close();
            }
            $this->poll->done();
        }
    }

    /**
     * @param string $data
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    private function push($data)
    {
        return new Promise(function (callable $resolve, callable $reject) use ($data) {
            $length = strlen($data);

            $resource = \eio_write(
                $this->handle,
                $data,
                $length,
                $this->append ? $this->size : $this->position,
                null,
                function ($data, $result, $req) use ($resolve, $reject, $length) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Writing to the file failed: %s.', \eio_get_last_error($req))
                        ));
                        $this->close();
                    } elseif ($this->queue->isEmpty()) {
                        $reject(new FileException('No pending write, the file may have been closed.'));
                    } else {
                        $this->queue->shift();

                        if ($this->append) {
                            $this->size += $result;
                        } else {
                            $this->position += $result;
                            if ($this->position > $this->size) {
                                $this->size = $this->position;
                            }
                        }

                        $resolve($result);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('Could not initialize file write.');
            }

            return function () use ($resource) {
                \eio_cancel($resource);
            };
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = \SEEK_SET, $timeout = 0)
    {
        if (!$this->isOpen()) {
            throw new UnseekableException('The file is no longer seekable.');
        }

        $offset = (int) $offset;

        switch ($whence) {
            case \SEEK_SET:
                break;

            case \SEEK_CUR:
                $offset = $this->position + $offset;
                break;

            case \SEEK_END:
                $offset = $this->size + $offset;
                break;

            default:
                throw new InvalidArgumentError('Invalid whence value. Use SEEK_SET, SEEK_CUR, or SEEK_END.');
        }

        if (0 > $offset) {
            throw new OutOfBoundsException(sprintf('Invalid offset: %s.', $offset));
        }

        $this->position = $offset;

        if ($this->position > $this->size) {
            $this->size = $this->position;
        }

        yield $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function getLength()
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function truncate($size)
    {
        $size = (int) $size;
        if (0 >= $size) {
            $size = 0;
        }

        $promise = new Promise(function (callable $resolve, callable $reject) use ($size) {
            $resource = \eio_ftruncate(
                $this->handle,
                $size,
                null,
                function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Truncating the file failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve(true);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('Could not truncate file.');
            }

            return function () use ($resource) {
                \eio_cancel($resource);
            };
        });

        $this->poll->listen();

        try {
            yield $promise;

            $this->size = $size;
            if ($this->position > $size) {
                $this->position = $size;
            }
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat()
    {
        $promise = new Promise(function (callable $resolve, callable $reject) {
            $resource = \eio_fstat($this->handle, null, function ($data, $result, $req) use ($resolve, $reject) {
                if (-1 === $result) {
                    $reject(new FileException(
                        sprintf('Getting file status failed: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $resolve($result);
                }
            });

            if (false === $resource) {
                throw new FileException('Could not initialize getting file status.');
            }

            return function () use ($resource) {
                \eio_cancel($resource);
            };
        });

        $this->poll->listen();

        try {
            yield $promise;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chown($uid)
    {
        return $this->chowngrp($uid, -1);
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp($gid)
    {
        return $this->chowngrp(-1, $gid);
    }

    /**
     * @coroutine
     *
     * @param int $uid
     * @param int $gid
     *
     * @return \Generator
     *
     * @resolve bool
     */
    private function chowngrp($uid, $gid)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($uid, $gid) {
            $resource = @\eio_fchown(
                $this->handle,
                $uid,
                $gid,
                null,
                function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Changing the file owner or group failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve(true);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('Invalid uid and/or gid.');
            }

            return function () use ($resource) {
                \eio_cancel($resource);
            };
        });

        $this->poll->listen();

        try {
            yield $promise;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($mode)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($mode) {
            $resource = \eio_fchmod(
                $this->handle,
                $mode,
                null,
                function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Changing the file mode failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve(true);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('Invalid mode.');
            }

            return function () use ($resource) {
                \eio_cancel($resource);
            };
        });

        $this->poll->listen();

        try {
            yield $promise;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve int
     */
    public function copy($path)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($path) {
            $resource = \eio_open(
                $path,
                \EIO_O_WRONLY | \EIO_O_CREAT | \EIO_O_TRUNC,
                0644,
                null,
                function ($data, $handle, $req) use ($resolve, $reject) {
                    if (-1 === $handle) {
                        $reject(new FileException(
                            sprintf('Opening the file failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve($handle);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('Could not open file.');
            }

            return function () use ($resource) {
                \eio_cancel($resource);
            };
        });

        $this->poll->listen();

        try {
            $handle = (yield $promise);
        } finally {
            $this->poll->done();
        }

        $promise = new Promise(function (callable $resolve, callable $reject) use ($handle) {
            $resource = \eio_sendfile(
                $handle,
                $this->handle,
                0,
                $this->size,
                null,
                function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Copying the file failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve(true);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('Could not copy file.');
            }

            return function () use ($resource) {
                \eio_cancel($resource);
            };
        });

        $this->poll->listen();

        try {
            yield $promise;
        } finally {
            $this->poll->done();
            \eio_close($handle);
        }

        yield $this->size;
    }
}
