<?php
namespace Icicle\File\Concurrent\Internal;

use Icicle\File\Exception\FileException;

class File
{
    /**
     * @var resource
     */
    private $handle;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $mode;

    public function __construct($path, $mode)
    {
        $handle = @fopen($path, $mode);

        if (!$handle) {
            $message = 'Could not open the file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        $this->handle = $handle;
        $this->path = $path;
        $this->mode = $mode;
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * @param int $length
     *
     * @return string
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function read($length)
    {
        $data = @fread($this->handle, $length);

        if (false === $data) {
            $message = 'Could not read from the file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $data;
    }

    /**
     * @param string $data
     *
     * @return int
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function write($data)
    {
        $length = strlen($data);

        if (0 === $length) {
            return 0;
        }

        $written = @fwrite($this->handle, $data, $length);

        if (false === $written || $written !== $length) {
            $message = 'Could not write to the file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $written;
    }

    /**
     * @param int $offset
     * @param int $whence
     *
     * @return int
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function seek($offset, $whence = \SEEK_SET)
    {
        if (-1 === fseek($this->handle, $offset, $whence)) {
            $message = 'Could not move file pointer.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        $position = ftell($this->handle);

        if (false === $position) {
            $message = 'Could not get the file pointer position.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $position;
    }

    /**
     * @return int
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function tell()
    {
        $position = ftell($this->handle);

        if (false === $position) {
            $message = 'Could not get file pointer position.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $position;
    }

    /**
     * @return int
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function size()
    {
        $size = @filesize($this->path);

        if (false === $size) {
            $message = 'Could not get file size.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $size;
    }

    /**
     * @return array
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function stat()
    {
        $result = fstat($this->handle);

        if (false === $result) {
            $message = 'Could not stat file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $result;
    }

    /**
     * @param int $size
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function truncate($size)
    {
        if (!ftruncate($this->handle, (int) $size)) {
            $message = 'Could not truncate file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }

    /**
     * @param int $owner
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function chown($owner)
    {
        if (!chown($this->path, (int) $owner)) {
            $message = 'Could not change file owner.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }

    /**
     * @param int $group
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function chgrp($group)
    {
        if (!chgrp($this->path, (int) $group)) {
            $message = 'Could not change file group.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }

    /**
     * @param int $mode
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function chmod($mode)
    {
        if (!chmod($this->path, (int) $mode)) {
            $message = 'Could not change file mode.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }
}
