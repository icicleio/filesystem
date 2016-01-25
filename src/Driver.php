<?php
namespace Icicle\File;

interface Driver
{
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
     * @resolve \Icicle\File\FileInterface
     *
     * @throws \Icicle\File\Exception\FileException If the file is not found or cannot be opened.
     */
    public function open(string $path, string $mode): \Generator;

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException If unlinking fails.
     */
    public function unlink(string $path): \Generator;

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
    public function stat(string $path): \Generator;

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
     * @throws \Icicle\File\Exception\FileException If creatingn the hard link fails.
     */
    public function link(string $source, string $target): \Generator;

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
    public function symlink(string $source, string $target): \Generator;

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
    public function readlink(string $path): \Generator;

    /**
     * @coroutine
     *
     * @param string $oldPath
     * @param string $newPath
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function rename(string $oldPath, string $newPath): \Generator;

    /**
     * @coroutine
     *
     * @param string $source
     * @param string $target
     *
     * @return \Generator
     *
     * @resolve int Size of the copied file.
     *
     * @throws \Icicle\File\Exception\FileException If unlinking fails.
     */
    public function copy(string $source, string $target): \Generator;

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
    public function mkDir(string $path, int $mode = 0755): \Generator;

    /**
     * @coroutine
     *
     * @param $path
     *
     * @return \Generator
     *
     * @resolve string[]
     */
    public function lsDir(string $path): \Generator;

    /**
     * @coroutine
     *
     * @param $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function rmDir(string $path): \Generator;

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function isFile(string $path): \Generator;

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function isDir(string $path): \Generator;

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
    public function chown(string $path, int $uid): \Generator;

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
    public function chgrp(string $path, int $gid): \Generator;

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
    public function chmod(string $path, int $mode): \Generator;
}
