<?php
namespace Icicle\File\Eio;

use Icicle\Awaitable\Delayed;
use Icicle\File\Driver;
use Icicle\File\Exception\FileException;
use Icicle\Loop;

class EioDriver implements Driver
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
     * @var \Icicle\File\Eio\Internal\EioPoll
     */
    private $poll;

    /**
     * @return bool
     */
    public static function enabled()
    {
        return \extension_loaded('eio');
    }

    public function __construct()
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new FileException('Requires the eio extension.');
        } // @codeCoverageIgnoreEnd

        $this->poll = new Internal\EioPoll();
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
        $chmod = ($flags & \EIO_O_CREAT) ? 0644 : 0;

        $delayed = new Delayed();
        \eio_open($path, $flags, $chmod, null, function (Delayed $delayed, $handle, $req) {
            if (-1 === $handle) {
                $delayed->reject(new FileException(
                    sprintf('Opening the file failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve($handle);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            $handle = (yield $delayed);
        } finally {
            $this->poll->done();
        }

        if ($flags & \EIO_O_TRUNC) { // File truncated.
            $size = 0;
        } else {
            $delayed = new Delayed();
            \eio_fstat($handle, null, function (Delayed $delayed, $result, $req) {
                if (-1 === $result) {
                    $delayed->reject(new FileException(
                        sprintf('Finding file size failed: %s.', \eio_get_last_error($req))
                    ));
                } else {
                    $delayed->resolve($result['size']);
                }
            }, $delayed);

            $this->poll->listen();

            try {
                $size = (yield $delayed);
            } finally {
                $this->poll->done();
            }
        }

        yield new EioFile($this->poll, $handle, $path, $size, (bool) ($flags & \EIO_O_APPEND));
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path)
    {
        if (!(yield $this->isFile($path))) { // Ensure file exists before attempting to unlink.
            throw new FileException('File does not exist or is a directory.');
        }

        $delayed = new Delayed();
        \eio_unlink($path, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Unlinking the file failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            yield $delayed;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename($oldPath, $newPath)
    {
        $delayed = new Delayed();
        \eio_rename($oldPath, $newPath, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Renaming the file failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            yield $delayed;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat($path)
    {
        $delayed = new Delayed();
        \eio_stat($path, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Could not stat file: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve($result);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            $stat = (yield $delayed);
        } finally {
            $this->poll->done();
        }

        $numeric = [];
        foreach (self::getStatKeys() as $key => $name) {
            $numeric[$key] = $stat[$name];
        }

        yield array_merge($numeric, $stat);
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
    public function link($source, $target)
    {
        return $this->doLink($source, $target, true);
    }

    /**
     * {@inheritdoc}
     */
    public function symlink($source, $target)
    {
        return $this->doLink($source, $target, false);
    }

    /**
     * {@inheritdoc}
     */
    private function doLink($source, $target, $hard)
    {
        $callback = function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Could not create link: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        };

        $delayed = new Delayed();

        if ($hard) {
            \eio_link($source, $target, null, $callback, $delayed);
        } else {
            \eio_symlink($source, $target, null, $callback, $delayed);
        }

        $this->poll->listen();

        try {
            yield $delayed;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readlink($path)
    {
        $delayed = new Delayed();
        \eio_readlink($path, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Could not read symlink: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve($result);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            yield $delayed;
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
    public function mkDir($path, $mode = 0755)
    {
        $delayed = new Delayed();
        \eio_mkdir($path, $mode, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Could not create directory: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            yield $delayed;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lsDir($path)
    {
        $delayed = new Delayed();
        \eio_readdir($path, 0, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Could not read directory: %s.', \eio_get_last_error($req))
                ));
            } else {
                $result = $result['names'];
                sort($result, \SORT_STRING | \SORT_NATURAL | \SORT_FLAG_CASE);
                $delayed->resolve($result);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            yield $delayed;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rmDir($path)
    {
        $delayed = new Delayed();
        \eio_rmdir($path, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Could not remove directory: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            yield $delayed;
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
        $delayed = new Delayed();
        \eio_chown($path, $uid, $gid, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Changing the owner or group failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            yield $delayed;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($path, $mode)
    {
        $delayed = new Delayed();
        \eio_chmod($path, $mode, null, function (Delayed $delayed, $result, $req) {
            if (-1 === $result) {
                $delayed->reject(new FileException(
                    sprintf('Changing the owner failed: %s.', \eio_get_last_error($req))
                ));
            } else {
                $delayed->resolve(true);
            }
        }, $delayed);

        $this->poll->listen();

        try {
            yield $delayed;
        } finally {
            $this->poll->done();
        }
    }

    /**
     * @return string[]
     */
    public static function getStatKeys()
    {
        return self::$statKeys;
    }
}
