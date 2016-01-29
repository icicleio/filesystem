<?php
namespace Icicle\File;

use Icicle\File\{Concurrent\ConcurrentDriver, Eio\EioDriver};

if (!\function_exists(__NAMESPACE__ . '\driver')) {
    /**
     * @param \Icicle\File\Driver|null $driver
     *
     * @return \Icicle\File\Driver
     */
    function driver(Driver $driver = null): Driver
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
     *
     * @codeCoverageIgnore
     */
    function create(): Driver
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
    function get(string $path): \Generator
    {
        /** @var \Icicle\File\File $file */
        $file = yield from open($path, 'r');

        $data = '';

        try {
            while (!$file->eof()) {
                $data .= yield from $file->read();
            }
        } finally {
            $file->close();
        }

        return $data;
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
    function put(string $path, string $data, bool $append = false): \Generator
    {
        /** @var \Icicle\File\File $file */
        $file = yield from open($path, $append ? 'a' : 'w');

        try {
            $written = yield from $file->write($data);
        } finally {
            $file->close();
        }

        return $written;
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
    function open(string $path, string $mode): \Generator
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
    function unlink(string $path): \Generator
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
    function stat(string $path): \Generator
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
    function rename(string $oldPath, string $newPath): \Generator
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
    function link(string $source, string $target): \Generator
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
    function readlink(string $path): \Generator
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
    function symlink(string $source, string $target): \Generator
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
    function copy(string $source, string $target): \Generator
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
    function isFile(string $path): \Generator
    {
        return driver()->isFile($path);
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
    function isDir(string $path): \Generator
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
    function mkDir(string $path, int $mode = 0755): \Generator
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
    function lsDir(string $path): \Generator
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
    function rmDir(string $path): \Generator
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
    function chown(string $path, int $uid): \Generator
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
    function chgrp(string $path, int $gid): \Generator
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
    function chmod(string $path, int $mode): \Generator
    {
        return driver()->chmod($path, $mode);
    }
}
