<?php
namespace Icicle\File;

use Icicle\File\Concurrent\ConcurrentDriver;
use Icicle\File\Eio\EioDriver;

if (!\function_exists(__NAMESPACE__ . '\driver')) {
    /**
     * @param \Icicle\File\Driver|null $driver
     *
     * @return \Icicle\File\Driver
     */
    function driver(Driver $driver = null)
    {
        static $instance;

        if (null !== $driver) {
            $instance = $driver;
        } elseif (null === $instance) {
            $instance = create();
        }

        return $instance;
    }

    /**
     * @return \Icicle\File\Driver
     */
    function create()
    {
        if (EioDriver::enabled()) {
            return new EioDriver();
        }

        return new ConcurrentDriver();
    }

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve string File contents.
     *
     * @throws \Icicle\File\Exception\FileException
     */
    function get($path)
    {
        /** @var \Icicle\File\File $file */
        $file = (yield driver()->open($path, 'r'));

        $data = '';

        while (!$file->eof()) {
            $data .= (yield $file->read());
        }

        $file->close();

        yield $data;
    }

    /**
     * @coroutine
     *
     * @param string $path
     * @param string $data
     * @param bool $append
     *
     * @return \Generator
     *
     * @resolve int Number of bytes written.
     *
     * @throws \Icicle\File\Exception\FileException
     */
    function put($path, $data, $append = false)
    {
        /** @var \Icicle\File\File $file */
        $file = (yield driver()->open($path, $append ? 'a' : 'w'));

        $written = (yield $file->write($data));

        $file->close();

        yield $written;
    }

    /**
     * Opens the file at the file path using the given mode. Modes are identical to those of fopen().
     *
     * @coroutine
     *
     * @param string $path
     * @param string $mode
     *
     * @return \Generator
     *
     * @resolve \Icicle\File\File
     *
     * @throws \Icicle\File\Exception\FileException If the file is not found or cannot be opened.
     */
    function open($path, $mode)
    {
        return driver()->open($path, $mode);
    }

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException If unlinking the file fails.
     */
    function unlink($path)
    {
        return driver()->unlink($path);
    }

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve array
     *
     * @throws \Icicle\File\Exception\FileException If getting the file stats fails.
     */
    function stat($path)
    {
        return driver()->stat($path);
    }

    /**
     * @coroutine
     *
     * @param string $oldPath
     * @param string $newPath
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException If renaming the file fails.
     */
    function rename($oldPath, $newPath)
    {
        return driver()->rename($oldPath, $newPath);
    }

    /**
     * @coroutine
     *
     * @param string $source
     * @param string $target
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException If creating the hard link fails.
     */
    function link($source, $target)
    {
        return driver()->link($source, $target);
    }

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException If reading the symlink fails.
     */
    function readlink($path)
    {
        return driver()->readlink($path);
    }

    /**
     * @coroutine
     *
     * @param string $source
     * @param string $target
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException If creating the symlink fails.
     */
    function symlink($source, $target)
    {
        return driver()->symlink($source, $target);
    }

    /**
     * @coroutine
     *
     * @param string $source
     * @param string $target
     *
     * @return \Generator
     *
     * @resolve int Size of copied file.
     *
     * @throws \Icicle\File\Exception\FileException If copying the file fails.
     */
    function copy($source, $target)
    {
        return driver()->copy($source, $target);
    }

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException If determining if the path is a file fails.
     */
    function isFile($path)
    {
        return driver()->isfile($path);
    }

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException If determining if the path is a directory fails.
     */
    function isDir($path)
    {
        return driver()->isDir($path);
    }

    /**
     * @coroutine
     *
     * @param string $path
     * @param int $mode
     *
     * @return \Generator
     *
     * @resolve bool
     */
    function mkDir($path, $mode = 0755)
    {
        return driver()->mkDir($path, $mode);
    }

    /**
     * @coroutine
     *
     * @param $path
     *
     * @return \Generator
     *
     * @resolve string[]
     */
    function lsDir($path)
    {
        return driver()->lsDir($path);
    }

    /**
     * @coroutine
     *
     * @param $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    function rmDir($path)
    {
        return driver()->rmDir($path);
    }

    /**
     * @coroutine
     *
     * @param string $path
     * @param int $uid
     *
     * @return \Generator
     *
     * @resolve bool
     */
    function chown($path, $uid)
    {
        return driver()->chown($path, $uid);
    }

    /**
     * @coroutine
     *
     * @param string $path
     * @param int $gid
     *
     * @return \Generator
     *
     * @resolve bool
     */
    function chgrp($path, $gid)
    {
        return driver()->chgrp($path, $gid);
    }

    /**
     * @coroutine
     *
     * @param string $path
     * @param int $mode
     *
     * @return \Generator
     *
     * @resolve bool
     */
    function chmod($path, $mode)
    {
        return driver()->chmod($path, $mode);
    }
}
