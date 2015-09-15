<?php
namespace Icicle\File\Concurrent\Internal;

use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\TaskInterface;
use Icicle\File\Exception\FileException;
use Icicle\File\Exception\InvalidArgumentError;

class FileTask implements TaskInterface
{
    /**
     * @var string
     */
    private $operation;

    /**
     * @var int
     */
    private $args;

    /**
     * @param string $operation
     * @param array $args
     *
     * @throws \Icicle\File\Exception\InvalidArgumentError
     */
    public function __construct($operation, array $args = [])
    {
        if (!strlen($operation)) {
            throw new InvalidArgumentError('Operation must be a non-empty string.');
        }

        $this->operation = $operation;
        $this->args = $args;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Icicle\File\Exception\FileException
     * @throws \Icicle\File\Exception\InvalidArgumentError
     */
    public function run(Environment $environment)
    {
        if ('f' === $this->operation[0]) {
            if ('fopen' === $this->operation) {
                if ($environment->exists('file')) {
                    throw new FileException('A file handle has already been opened on the worker.');
                }
                $file = new File($this->args[0], $this->args[1]);
                $environment->set('file', $file);
                return [$file->size(), $file->tell()];
            }

            if (!$environment->exists('file')) {
                throw new FileException('No file handle has been opened on the worker.');
            }

            if (!($file = $environment->get('file')) instanceof File) {
                throw new FileException('File storage found in inconsistent state.');
            }

            switch ($this->operation) {
                case 'fread':
                    return $file->read($this->args[0]);

                case 'fwrite':
                    return $file->write($this->args[0]);

                case 'fseek':
                    return $file->seek($this->args[0], $this->args[1]);

                case 'fstat':
                    return $file->stat();

                case 'ftruncate':
                    return $file->truncate($this->args[0]);

                case 'fchown':
                    return $file->chown($this->args[0]);

                case 'fchgrp':
                    return $file->chgrp($this->args[0]);

                case 'fchmod':
                    return $file->chmod($this->args[0]);

                default:
                    throw new InvalidArgumentError('Invalid operation.');
            }
        }

        switch ($this->operation) {
            case 'stat':
                return $this->stat($this->args[0]);

            case 'unlink':
                return $this->unlink($this->args[0]);

            case 'rename':
                return $this->rename($this->args[0], $this->args[1]);

            case 'copy':
                return $this->copy($this->args[0], $this->args[1]);

            case 'symlink':
                return $this->symlink($this->args[0], $this->args[1]);

            case 'isFile':
                return $this->isFile($this->args[0]);

            case 'isDir':
                return $this->isDir($this->args[0]);

            default:
                throw new InvalidArgumentError('Invalid operation.');
        }
    }

    /**
     * @param string $path
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function unlink($path)
    {
        if (!@unlink($path)) {
            $message = 'Could not unlink file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }

    /**
     * @param string $oldPath
     * @param string $newPath
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function rename($oldPath, $newPath)
    {
        if (!@rename($oldPath, $newPath)) {
            $message = 'Could not unlink file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }

    /**
     * @param string $path
     *
     * @return array
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function stat($path)
    {
        $result = @stat($path);

        if (false === $result) {
            $message = 'Could not unlink file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $result;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private function isFile($path)
    {
        return is_file($path);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private function isDir($path)
    {
        return is_dir($path);
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @return int
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function copy($source, $target)
    {
        if (!copy($source, $target)) {
            $message = 'Could not copy file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return filesize($target);
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function symlink($source, $target)
    {
        if (!symlink($source, $target)) {
            $message = 'Could not create symlink.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }
}
