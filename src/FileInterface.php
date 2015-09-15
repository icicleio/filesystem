<?php
namespace Icicle\File;

use Icicle\Stream\DuplexStreamInterface;
use Icicle\Stream\SeekableStreamInterface;

interface FileInterface extends DuplexStreamInterface, SeekableStreamInterface
{
    const CHUNK_SIZE = 8192;

    /**
     * @return bool
     */
    public function eof();

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve array
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function stat();

    /**
     * @coroutine
     *
     * @param int $size
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function truncate($size);

    /**
     * @coroutine
     *
     * @param int $uid
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function chown($uid);

    /**
     * @coroutine
     *
     * @param int $gid
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function chgrp($gid);

    /**
     * @coroutine
     *
     * @param int $mode
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function chmod($mode);
}
