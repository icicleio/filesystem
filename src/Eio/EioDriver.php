<?php
namespace Icicle\File\Eio;

use Icicle\File\DriverInterface;
use Icicle\File\Exception\FileException;
use Icicle\Loop;
use Icicle\Promise\Promise;

class EioDriver implements DriverInterface
{
    /**
     * @var string[]
     */
    private static $statKeys = [
        0  => 'dev',
        1  => 'ino',
        2  => 'mode',
        3  => 'nlink',
        4  => 'uid',
        5  => 'gid',
        6  => 'rdev',
        7  => 'size',
        8  => 'atime',
        9  => 'mtime',
        10 => 'ctime',
        11 => 'blksize',
        12 => 'blocks',
    ];

    /**
     * @var \Icicle\File\Eio\EioPoll
     */
    private $poll;

    public function __construct()
    {
        // @codeCoverageIgnoreStart
        if (!\extension_loaded('eio')) {
            throw new FileException('Requires the eio extension.');
        } // @codeCoverageIgnoreEnd

        $this->poll = new EioPoll();
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

        if ($flags & \EIO_O_TRUNC) { // File truncated.
            $size = 0;
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

            $this->poll->listen();

            try {
                $size = (yield $promise);
            } finally {
                $this->poll->done();
            }
        }

        yield new EioFile($this->poll, $handle, $size, (bool) ($flags & \EIO_O_APPEND));
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
            $stat = (yield $promise);
        } finally {
            $this->poll->done();
        }

        $numeric = [];
        foreach (self::$statKeys as $key => $name) {
            $numeric[$key] = $stat[$name];
        }

        yield array_merge($numeric, $stat);
    }

    /**
     * {@inheritdoc}
     */
    public function isfile($path)
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
    public function isdir($path)
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

    /**
     * {@inheritdoc}
     */
    public function mkdir($path, $mode = 0755)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($path, $mode) {
            $resource = \eio_mkdir($path, $mode, null, function ($data, $result, $req) use ($resolve, $reject) {
                if (-1 === $result) {
                    $reject(new FileException(
                        sprintf('Could not create directory: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $resolve(true);
                }
            });

            if (false === $resource) {
                throw new FileException('Could not create directory.');
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
    public function readdir($path)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($path) {
            $resource = \eio_readdir($path, 0, null, function ($data, $result, $req) use ($resolve, $reject) {
                if (-1 === $result) {
                    $reject(new FileException(
                        sprintf('Could not create directory: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $result = $result['names'];
                    sort($result, \SORT_STRING | \SORT_NATURAL | \SORT_FLAG_CASE);
                    $resolve($result);
                }
            });

            if (false === $resource) {
                throw new FileException('Could not create directory.');
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
    public function rmdir($path)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($path) {
            $resource = \eio_rmdir($path, null, function ($data, $result, $req) use ($resolve, $reject) {
                if (-1 === $result) {
                    $reject(new FileException(
                        sprintf('Could not remove directory: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $resolve(true);
                }
            });

            if (false === $resource) {
                throw new FileException('Could not remove directory.');
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
    public function chown($path, $uid)
    {
        return $this->chowngrp($path, $uid, -1);
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp($path, $gid)
    {
        return $this->chowngrp($path, -1, $gid);
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
    private function chowngrp($path, $uid, $gid)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($path, $uid, $gid) {
            $resource = @\eio_chown(
                $path,
                $uid,
                $gid,
                null,
                function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Changing the owner or group failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve(true);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('File not found or invalid uid and/or gid.');
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
    public function chmod($path, $mode)
    {
        $promise = new Promise(function (callable $resolve, callable $reject) use ($path, $mode) {
            $resource = \eio_fchmod(
                $path,
                $mode,
                null,
                function ($data, $result, $req) use ($resolve, $reject) {
                    if (-1 === $result) {
                        $reject(new FileException(
                            sprintf('Changing the owner failed: %s.', \eio_get_last_error($req))
                        ));
                    } else {
                        $resolve(true);
                    }
                }
            );

            if (false === $resource) {
                throw new FileException('File not found or invalid mode.');
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
}
