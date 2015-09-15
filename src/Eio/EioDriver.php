<?php
namespace Icicle\File\Eio;

use Icicle\File\DriverInterface;
use Icicle\File\Exception\FileException;
use Icicle\Loop;
use Icicle\Promise\Promise;

class EioDriver implements DriverInterface
{
    /**
     * @var \Icicle\File\Eio\EioPoll
     */
    private $poll;

    public function __construct()
    {
        if (!\extension_loaded('eio')) {
            throw new FileException('Requires the eio extension.');
        }

        $this->poll = new EioPoll();
    }

    /**
     * Should be called after forking.
     */
    public function reInit()
    {
        $this->poll->reInit();
    }

    /**
     * @param string $mode
     *
     * @return int
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function makeFlags($mode)
    {
        return \EIO_O_NONBLOCK | \EIO_O_FSYNC | $this->parseMode($mode);
    }

    /**
     * @param string $mode
     *
     * @return int
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function parseMode($mode)
    {
        $mode = str_replace(['b', 't'], '', $mode);

        switch ($mode) {
            case 'r':  return \EIO_O_RDONLY;
            case 'r+': return \EIO_O_RDWR;
            case 'w':  return \EIO_O_WRONLY | \EIO_O_TRUNC | \EIO_O_CREAT;
            case 'w+': return \EIO_O_RDWR | \EIO_O_TRUNC | \EIO_O_CREAT;
            case 'a':  return \EIO_O_WRONLY | \EIO_O_APPEND | \EIO_O_CREAT;
            case 'a+': return \EIO_O_RDWR | \EIO_O_APPEND | \EIO_O_CREAT;
            case 'x':  return \EIO_O_WRONLY | \EIO_O_CREAT | \EIO_O_EXCL;
            case 'x+': return \EIO_O_RDWR | \EIO_O_CREAT | \EIO_O_EXCL;
            case 'c':  return \EIO_O_WRONLY | \EIO_O_CREAT;
            case 'c+': return \EIO_O_RDWR | \EIO_O_CREAT;

            default:
                throw new FileException('Invalid file mode.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($path, $mode)
    {
        $flags = $this->makeFlags($mode);

        $promise = new Promise(function (callable $resolve, callable $reject) use ($path, $mode, $flags) {
            $chmod = ($flags & \EIO_O_CREAT) ? 0644 : 0;

            $resource = \eio_open($path, $flags, $chmod, null, function ($data, $handle, $req) use (
                $resolve, $reject
            ) {
                if (-1 === $handle) {
                    $reject(new FileException(
                        sprintf('Opening the file failed: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $resolve($handle);
                }
            });

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

        if ($flags & \EIO_O_TRUNC) {
            $promise = new Promise(function (callable $resolve, callable $reject) use ($handle) {
                $resource = \eio_ftruncate($handle, 0, null, function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Truncating the file failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve(0);
                    }
                });

                if (false === $resource) {
                    throw new FileException('Could not truncate file.');
                }

                return function () use ($resource) {
                    \eio_cancel($resource);
                };
            });
        } else {
            $promise = new Promise(function (callable $resolve, callable $reject) use ($handle) {
                $resource = \eio_fstat($handle, null, function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Finding file size failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve($result['size']);
                    }
                });

                if (false === $resource) {
                    throw new FileException('Could not get file size.');
                }

                return function () use ($resource) {
                    \eio_cancel($resource);
                };
            });
        }

        $this->poll->listen();

        try {
            $size = (yield $promise);
        } finally {
            $this->poll->done();
        }

        yield new EioFile($this->poll, $handle, $size, $flags & \EIO_O_APPEND ? $size : 0);
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($path) {
            $resource = \eio_unlink($path, null, function ($data, $result, $req) use ($resolve, $reject) {
                if (-1 === $result) {
                    $reject(new FileException(
                        sprintf('Unlinking the file failed: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $resolve(true);
                }
            });

            if (false === $resource) {
                throw new FileException('Could not unlink file.');
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
    public function rename($oldPath, $newPath)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($oldPath, $newPath) {
            $resource = \eio_rename($oldPath, $newPath, null, function ($data, $result, $req) use ($resolve, $reject) {
                if (-1 === $result) {
                    $reject(new FileException(
                        sprintf('Renaming the file failed: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $resolve(true);
                }
            });

            if (false === $resource) {
                throw new FileException('Could not rename file.');
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
    public function stat($path)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($path) {
            $resource = \eio_stat($path, null, function ($data, $result, $req) use ($resolve, $reject) {
                if (-1 === $result) {
                    $reject(new FileException(
                        sprintf('Could not stat file: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $resolve($result);
                }
            });

            if (false === $resource) {
                throw new FileException('Could not stat file.');
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
    public function isFile($path)
    {
        try {
            $result = (yield $this->stat($path));
            yield (bool) ($result['mode'] & \EIO_S_IFREG);
        } catch (FileException $exception) {
            yield false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isDir($path)
    {
        try {
            $result = (yield $this->stat($path));
            yield !($result['mode'] & \EIO_S_IFREG);
        } catch (FileException $exception) {
            yield false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function symlink($source, $target)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($source, $target) {
            $resource = \eio_symlink($source, $target, null, function ($data, $result, $req) use ($resolve, $reject) {
                if (-1 === $result) {
                    $reject(new FileException(
                        sprintf('Could not create symlink: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $resolve(true);
                }
            });

            if (false === $resource) {
                throw new FileException('Could not create symlink.');
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
    public function copy($source, $target)
    {
        /** @var \Icicle\File\Eio\EioFile $file */
        $file = (yield $this->open($source, 'r'));
        $written = (yield $file->copy($target));
        $file->close();
        yield $written;
    }
}
