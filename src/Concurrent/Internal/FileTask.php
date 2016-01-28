<?php
namespace Icicle\File\Concurrent\Internal;

use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\Task;
use Icicle\Exception\InvalidArgumentError;
use Icicle\File\Exception\FileException;

class FileTask implements Task
{
    /**
     * @var string
     */
    private $operation;

    /**
     * @var mixed[]
     */
    private $args;

    /**
     * @var string|null
     */
    private $id;

    /**
     * @param string $operation
     * @param array $args
     * @param int $id File ID.
     *
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    public function __construct($operation, array $args = [], $id = 0)
    {
        if (!strlen($operation)) {
            throw new InvalidArgumentError('Operation must be a non-empty string.');
        }

        $this->operation = $operation;
        $this->args = $args;

        if (0 !== $id) {
            $this->id = $this->makeId($id);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Icicle\File\Exception\FileException
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    public function run(Environment $environment)
    {
        if ('f' === $this->operation[0]) {
            if ('fopen' === $this->operation) {
                $file = new File($this->args[0], $this->args[1]);
                $id = $file->getId();
                $environment->set($this->makeId($id), $file);
                return [$id, $file->stat()['size'], $file->inAppendMode()];
            }

            if (null === $this->id) {
                throw new FileException('No file ID provided.');
            }

            if (!$environment->exists($this->id)) {
                throw new FileException('No file handle with the given ID has been opened on the worker.');
            }

            if (!($file = $environment->get($this->id)) instanceof File) {
                throw new FileException('File storage found in inconsistent state.');
            }

            switch ($this->operation) {
                case 'fread':
                case 'fwrite':
                case 'fseek':
                case 'fstat':
                case 'ftruncate':
                    return call_user_func_array([$file, substr($this->operation, 1)], $this->args);

                case 'fclose':
                    $environment->delete($this->id);
                    return true;

                default:
                    throw new InvalidArgumentError('Invalid operation.');
            }
        }

        switch ($this->operation) {
            case 'stat':
            case 'unlink':
            case 'rename':
            case 'copy':
            case 'link':
            case 'symlink':
            case 'readlink':
            case 'isfile':
            case 'isdir':
            case 'mkdir':
            case 'lsdir':
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
     * @param int $id
     *
     * @return string
     */
    private function makeId($id)
    {
        return '__file_' . $id;
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
            $message = 'Could not rename file.';
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
            $message = 'Could not stat file.';
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
        if (!@copy($source, $target)) {
            $message = 'Could not copy file.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $this->stat($target)['size'];
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @return bool
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function link($source, $target)
    {
        if (!@link($source, $target)) {
            $message = 'Could not create link.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
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
        if (!@symlink($source, $target)) {
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
     *
     * @return string
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function readlink($path)
    {
        $result = @readlink($path);

        if (false === $result) {
            $message = 'Could not read symlink.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return $result;
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
    private function lsdir($path)
    {
        $result = @scandir($path);

        if (false === $result) {
            $message = 'Could not read directory.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return array_values(array_diff($result, ['.', '..']));
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
        if (!@chown($path, (int) $owner)) {
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
        if (!@chgrp($path, (int) $group)) {
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
        if (!@chmod($path, (int) $mode)) {
            $message = 'Could not change file mode.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FileException($message);
        }

        return true;
    }
}
