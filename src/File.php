<?php
namespace Icicle\File;

use Icicle\Stream\{DuplexStream, SeekableStream};

interface File extends DuplexStream, SeekableStream
{
    const CHUNK_SIZE = 8192;

    /**
     * Returns the path used to open the file.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * @return bool
     */
    public function eof(): bool;

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve array
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function stat(): \Generator;

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
    public function truncate(int $size): \Generator;

    /**
     * @coroutine
     *
     * @param string $path
     *
     * @return \Generator
     *
     * @resolve int Size of copied file.
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function copy(string $path): \Generator;

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
    public function chown(int $uid): \Generator;

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
    public function chgrp(int $gid): \Generator;

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
    public function chmod(int $mode): \Generator;
}
