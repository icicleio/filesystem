<?php
namespace Icicle\File;

interface DriverInterface
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
     * @throws \Icicle\File\Exception\FileException If unlinking fails.
     */
    public function symlink($source, $target);

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
    public function mkdir($path, $mode = 0777);

    /**
     * @coroutine
     *
     * @param $path
     *
     * @return \Generator
     *
     * @resolve string[]
     */
    public function readdir($path);

    /**
     * @coroutine
     *
     * @param $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function rmdir($path);

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function isfile($path);

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve bool
     */
    public function isdir($path);

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
