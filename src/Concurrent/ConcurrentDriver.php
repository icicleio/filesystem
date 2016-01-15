<?php
namespace Icicle\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker\DefaultPool;
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
        $this->pool = $pool ?: new DefaultPool();
        if (!$this->pool->isRunning()) {
            $this->pool->start();
        }
    }

    public function __destruct()
    {
        if ($this->pool->isRunning()) {
            $this->pool->kill();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($path, $mode)
    {
        $worker = $this->pool->get();
        
        $task = new Internal\FileTask('fopen', [(string) $path, (string) $mode]);

        try {
            list($id, $size, $append) = (yield $worker->enqueue($task));
        } catch (TaskException $exception) {
            throw new FileTaskException('Opening the file failed.', $exception);
        }

        yield new ConcurrentFile($worker, $id, $path, $size, $append);
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('unlink', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Unlinking the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('stat', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Stating the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename($oldPath, $newPath)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('rename', [(string) $oldPath, (string) $newPath]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Renaming the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isFile($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('isfile', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Determining if path is a file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isDir($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('isdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Determine if the path is a directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function link($source, $target)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('link', [(string) $source, (string) $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the link failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function symlink($source, $target)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('symlink', [(string) $source, (string) $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the symlink failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readlink($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('readlink', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the symlink failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copy($source, $target)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('copy', [(string) $source, (string) $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Copying the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mkDir($path, $mode = 0755)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('mkdir', [(string) $path, (int) $mode]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lsDir($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('lsdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rmDir($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('rmdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($path, $mode)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('chmod', [(string) $path, (int) $mode]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chown($path, $uid)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('chown', [(string) $path, (int) $uid]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp($path, $gid)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('chgrp', [(string) $path, (int) $gid]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        }
    }
}
