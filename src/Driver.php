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
    public function open($path, $mode);

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
    public function unlink($path);

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
    public function stat($path);

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
    public function link($source, $target);

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
    public function symlink($source, $target);

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
    public function readlink($path);

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
    public function rename($oldPath, $newPath);

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
    public function copy($source, $target);

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
    public function mkDir($path, $mode = 0755);

    /**
     * @coroutine
     *
     * @param $path
     *
     * @return \Generator
     *
     * @resolve string[]
     */
    public function lsDir($path);

    /**
     * @coroutine
     *
     * @param $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function rmDir($path);

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function isFile($path);

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function isDir($path);

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
    public function chown($path, $uid);

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
    public function chgrp($path, $gid);

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
    public function chmod($path, $mode);
}
