<?php
namespace Icicle\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker;
use Icicle\Concurrent\Worker\Pool;
use Icicle\File\Driver;
use Icicle\File\Exception\FileTaskException;

class ConcurrentDriver implements Driver
{
    /**
     * @var \Icicle\Concurrent\Worker\Pool
     */
    private $pool;

    /**
     * @param \Icicle\Concurrent\Worker\Pool|null $pool
     */
    public function __construct(Pool $pool = null)
    {
        $this->pool = $pool ?: Worker\pool();
        if (!$this->pool->isRunning()) {
            $this->pool->start();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $mode): \Generator
    {
        $worker = $this->pool->get();
        
        $task = new Internal\FileTask('fopen', [$path, $mode]);

        try {
            list($id, $size, $append) = yield from $worker->enqueue($task);
        } catch (TaskException $exception) {
            throw new FileTaskException('Opening the file failed.', $exception);
        }

        return new ConcurrentFile($worker, $id, $path, $size, $append);
    }

    /**
     * {@inheritdoc}
     */
    public function unlink(string $path): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('unlink', [$path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Unlinking the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat(string $path): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('stat', [$path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Stating the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $oldPath, string $newPath): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('rename', [$oldPath, $newPath]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Renaming the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isFile(string $path): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('isfile', [$path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Determining if path is a file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isDir(string $path): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('isdir', [$path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Determine if the path is a directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function link(string $source, string $target): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('link', [$source, $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the link failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function symlink(string $source, string $target): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('symlink', [$source, $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the symlink failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readlink(string $path): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('readlink', [$path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the symlink failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $source, string $target): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('copy', [$source, $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Copying the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mkDir(string $path, int $mode = 0755): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('mkdir', [$path, $mode]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lsDir(string $path): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('lsdir', [$path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rmDir(string $path): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('rmdir', [$path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('chmod', [$path, $mode]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chown(string $path, int $uid): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('chown', [$path, $uid]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp(string $path, int $gid): \Generator
    {
        try {
            return yield from $this->pool->enqueue(new Internal\FileTask('chgrp', [$path, $gid]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        }
    }
}
