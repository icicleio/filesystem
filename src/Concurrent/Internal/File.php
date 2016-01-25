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

    /**
     * @param string $path
     * @param string $mode
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function __construct(string $path, string $mode)
    {
        $mode = str_replace(['b', 't'], '', $mode);

        switch ($mode) {
            case 'r':
            case 'r+':
            case 'w':
            case 'w+':
            case 'a':
            case 'a+':
            case 'x':
            case 'x+':
            case 'c':
            case 'c+':
                break;

            default:
                throw new FileException('Invalid file mode.');
        }

        $this->path = $path;
        $this->mode = $mode;

        $this->handle = @fopen($this->path, $this->mode . 'b');

        if (!$this->handle) {
            $message = 'Could not open the file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int) $this->handle;
    }

    /**
     * @return bool
     */
    public function inAppendMode(): bool
    {
        return 'a' === $this->mode || 'a+' === $this->mode;
    }

    /**
     * @param int $length
     *
     * @return string
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function read(int $length): string
    {
        $data = @fread($this->handle, $length);

        if (false === $data || '' === $data) {
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
    public function write(string $data): int
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
    public function seek(int $offset, int $whence = \SEEK_SET): int
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
    public function tell(): int
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
    public function size(): int
    {
        return $this->stat()['size'];
    }

    /**
     * @return array
     *
     * @throws \Icicle\File\Exception\FileException
     */
    public function stat(): array
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
    public function truncate(int $size): bool
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
}
