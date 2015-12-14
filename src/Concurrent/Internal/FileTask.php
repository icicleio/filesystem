<?php
namespace Icicle\File\Concurrent\Internal;

use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\Task;
use Icicle\File\Exception\FileException;
use Icicle\File\Exception\InvalidArgumentError;

class FileTask implements Task
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
                return [$file->size(), $file->inAppendMode()];
            }

            if (!$environment->exists('file')) {
                throw new FileException('No file handle has been opened on the worker.');
            }

            if (!($file = $environment->get('file')) instanceof File) {
                throw new FileException('File storage found in inconsistent state.');
            }

            switch ($this->operation) {
                case 'fread':
                case 'fwrite':
                case 'fseek':
                case 'fstat':
                case 'ftruncate':
                case 'fchown':
                case 'fchgrp':
                case 'fchmod':
                    return call_user_func_array([$file, substr($this->operation, 1)], $this->args);

                default:
                    throw new InvalidArgumentError('Invalid operation.');
            }
        }

        switch ($this->operation) {
            case 'stat':
            case 'unlink':
            case 'rename':
            case 'copy':
            case 'symlink':
            case 'isfile':
            case 'isdir':
            case 'mkdir':
            case 'readdir':
            case 'rmdir':
            case 'chmod':
            case 'chown':
            case 'chgrp':
                return call_user_func_array([$this, $this->operation], $this->args);

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
    private function isfile($path)
    {
        return is_file($path);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private function isdir($path)
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

    /**
     * @param string $path
     * @param int $mode
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function mkdir($path, $mode = 0755)
    {
        if (!@mkdir($path, $mode)) {
            $message = 'Could not create directory.';
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
    private function readdir($path)
    {
        $result = @scandir($path);

        if (false === $result) {
            $message = 'Could not read directory.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return array_diff($result, ['.', '..']);
    }

    /**
     * @param string $path
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function rmdir($path)
    {
        if (!@rmdir($path)) {
            $message = 'Could not remove directory.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }

    /**
     * @param string $path
     * @param int $owner
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function chown($path, $owner)
    {
        if (!chown($path, (int) $owner)) {
            $message = 'Could not change file owner.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }

    /**
     * @param string $path
     * @param int $group
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function chgrp($path, $group)
    {
        if (!chgrp($path, (int) $group)) {
            $message = 'Could not change file group.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }

    /**
     * @param string $path
     * @param int $mode
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function chmod($path, $mode)
    {
        if (!chmod($path, (int) $mode)) {
            $message = 'Could not change file mode.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }
}
