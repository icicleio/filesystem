<?php
namespace Icicle\File\Eio;

use Icicle\Awaitable\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Exception\InvalidArgumentError;
use Icicle\File\{Exception\FileException, File};
use Icicle\Stream\Exception\{OutOfBoundsException, UnreadableException, UnseekableException, UnwritableException};

class EioFile implements File
{
    /**
     * @var \Icicle\File\Eio\EioPoll
     */
    private $poll;

    /**
     * @var int
     */
    private $handle;

    /**
     * @var string
     */
    private $path;

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
     * @param string $path
     * @param int $size
     * @param bool $append
     */
    public function __construct(EioPoll $poll, int $handle, string $path, int $size, bool $append = false)
    {
        $this->poll = $poll;
        $this->handle = $handle;
        $this->path = $path;
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
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen(): bool
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

        if (!$this->queue->isEmpty()) {
            $exception = new FileException('The file was closed.');
            do {
                $this->queue->shift()->cancel($exception);
            } while (!$this->queue->isEmpty());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool
    {
        return $this->position === $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length = 0, string $byte = null, float $timeout = 0): \Generator
    {
        if (!$this->isReadable()) {
            throw new UnreadableException('The file is no longer readable.');
        }

        if (0 > $length) {
            throw new InvalidArgumentError('The length must be a non-negative integer.');
        }

        if (0 === $length) {
            $length = self::CHUNK_SIZE;
        }

        $remaining = $this->size - $this->position;
        $length = $length > $remaining ? $remaining : $length;

        $delayed = new Delayed();

        \eio_read(
            $this->handle,
            $length,
            $this->position,
            null,
            function (Delayed $delayed, $result, $req) {
                if (-1 === $result) {
                    $delayed->reject(new FileException(
                        sprintf('Reading from file failed: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $delayed->resolve($result);
                }
            },
            $delayed
        );

        $this->poll->listen();

        if ($timeout) {
            $delayed = $delayed->timeout($timeout);
        }

        try {
            $data = yield $delayed;
        } finally {
            $this->poll->done();
        }

        $byte = strlen($byte) ? $byte[0] : null;

        if (null !== $byte && false !== ($position = strpos($data, $byte))) {
            $data = substr($data, 0, $position + 1);
        }

        $this->position += strlen($data);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        return $this->isOpen() && !$this->eof();
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data, float $timeout = 0): \Generator
    {
        return $this->send($data, $timeout, false);
    }

    /**
     * {@inheritdoc}
     */
    public function end(string $data = '', float $timeout = 0)
    {
        return $this->send($data, $timeout, true);
    }

    /**
     * @coroutine
     *
     * @param string $data
     * @param float|int $timeout
     * @param bool $end
     *
     * @return \Generator
     *
     * @resolve int Number of bytes written to the file.
     *
     * @throws \Icicle\Stream\Exception\UnwritableException
     */
    protected function send(string $data, float $timeout, bool $end = false): \Generator
    {
        if (!$this->isWritable()) {
            throw new UnwritableException('The file is no longer writable.');
        }

        if ($this->queue->isEmpty()) {
            $awaitable = $this->push($data);
        } else {
            $awaitable = $this->queue->top();
            $awaitable = $awaitable->then(function () use ($data) {
                return $this->push($data);
            });
        }

        $this->queue->push($awaitable);

        if ($end) {
            $this->writable = false;
        }

        if ($timeout) {
            $awaitable = $awaitable->timeout($timeout);
        }

        $this->poll->listen();

        try {
            return yield $awaitable;
        } catch (\Exception $exception) {
            $this->close();
            throw $exception;
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
     * @return \Icicle\Awaitable\Delayed
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function push(string $data): Awaitable
    {
        $length = strlen($data);
        $delayed = new Delayed();
        \eio_write(
            $this->handle,
            $data,
            $length,
            $this->append ? $this->size : $this->position,
            null,
            function (Delayed $delayed, $result, $req) use ($length) {
                if (-1 === $result) {
                    $delayed->reject(new FileException(
                        sprintf('Writing to the file failed: %s.', \eio_get_last_error($req))
                    ));
                } elseif ($this->queue->isEmpty()) {
                    $delayed->reject(new FileException('No pending write, the file may have been closed.'));
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

                    $delayed->resolve($result);
                }
            },
            $delayed
        );

        return $delayed;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = \SEEK_SET, float $timeout = 0): \Generator
    {
        if (!$this->isOpen()) {
            throw new UnseekableException('The file is no longer seekable.');
        }

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

        return $this->position;

        yield; // Unreachable, but makes method a coroutine.
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function getLength(): int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function truncate(int $size): \Generator
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        if (0 >= $size) {
            $size = 0;
        }

        $delayed = new Delayed();
        \eio_ftruncate($this->handle, $size, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Truncating the file failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            $result = yield $delayed;

            $this->size = $size;
            if ($this->position > $size) {
                $this->position = $size;
            }

            return $result;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat(): \Generator
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        $delayed = new Delayed();
        \eio_fstat($this->handle, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Getting file status failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve($result);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            $stat = yield $delayed;
        } finally {
            $this->poll->done();
        }

        $numeric = [];
        foreach (EioDriver::getStatKeys() as $key => $name) {
            $numeric[$key] = $stat[$name];
        }

        return array_merge($numeric, $stat);
    }

    /**
     * {@inheritdoc}
     */
    public function chown(int $uid): \Generator
    {
        return $this->chowngrp($uid, -1);
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp(int $gid): \Generator
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
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function chowngrp(int $uid, int $gid): \Generator
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        $delayed = new Delayed();
        \eio_fchown($this->handle, $uid, $gid, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Changing the file owner or group failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            return yield $delayed;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(int $mode): \Generator
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        $delayed = new Delayed();
        \eio_fchmod($this->handle, $mode, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Changing the file mode failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            return yield $delayed;
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
    public function copy(string $path): \Generator
    {
        $delayed = new Delayed();
        \eio_open(
            $path,
            \EIO_O_WRONLY | \EIO_O_CREAT | \EIO_O_TRUNC,
            0644,
            null,
            function (Delayed $delayed, $handle, $req) {
                if (-1 === $handle) {
                    $delayed->reject(new FileException(
                        sprintf('Opening the file failed: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $delayed->resolve($handle);
                }
            },
            $delayed
        );

        $this->poll->listen();

        try {
            $handle = yield $delayed;
        } finally {
            $this->poll->done();
        }

        $delayed = new Delayed();
        \eio_sendfile(
            $handle,
            $this->handle,
            0,
            $this->size,
            null,
            function (Delayed $delayed, $result, $req) {
                if (-1 === $result) {
                    $delayed->reject(new FileException(
                        sprintf('Copying the file failed: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $delayed->resolve(true);
                }
            },
            $delayed
        );

        $this->poll->listen();

        try {
            yield $delayed;
        } finally {
            $this->poll->done();
            \eio_close($handle);
        }

        return $this->size;
    }
}
