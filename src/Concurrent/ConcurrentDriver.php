<?php
namespace Icicle\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker;
use Icicle\Concurrent\Worker\PoolInterface;
use Icicle\Concurrent\Worker\WorkerFactory;
use Icicle\Concurrent\Worker\WorkerFactoryInterface;
use Icicle\File\DriverInterface;
use Icicle\File\Exception\FileException;

class ConcurrentDriver implements DriverInterface
{
    /**
     * @var \Icicle\Concurrent\Worker\WorkerFactoryInterface
     */
    private $factory;

    /**
     * @var \Icicle\Concurrent\Worker\PoolInterface
     */
    private $pool;

    /**
     * @param \Icicle\Concurrent\Worker\WorkerFactoryInterface|null $factory
     * @param \Icicle\Concurrent\Worker\PoolInterface|null $pool
     */
    public function __construct(WorkerFactoryInterface $factory = null, PoolInterface $pool = null)
    {
        $this->factory = $factory ?: new WorkerFactory();
        $this->pool = $pool ?: Worker\pool();

        if (!$this->pool->isRunning()) {
            $this->pool->start();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($path, $mode)
    {
        $worker = $this->factory->create();
        $worker->start();
        
        $task = new Internal\FileTask('fopen', [(string) $path, (string) $mode]);

        try {
            list($size, $append) = (yield $worker->enqueue($task));
        } catch (TaskException $exception) {
            yield $worker->shutdown();
            throw new FileException('Opening the file failed.', 0, $exception);
        }

        yield new ConcurrentFile($worker, $size, $append);
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('unlink', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileException('Unlinking the file failed.', 0, $exception);
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
            throw new FileException('Stating the file failed.', 0, $exception);
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
            throw new FileException('Renaming the file failed.', 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isfile($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('isfile', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileException('Determining if path is a file failed.', 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isdir($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('isdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileException('Determine if the path is a directory failed.', 0, $exception);
        }
    }

    public function symlink($source, $target)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('symlink', [(string) $source, (string) $target]));
        } catch (TaskException $exception) {
            throw new FileException('Creating the symlink failed.', 0, $exception);
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
            throw new FileException('Copying the file failed.', 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir($path, $mode = 0755)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('mkdir', [(string) $path, (int) $mode]));
        } catch (TaskException $exception) {
            throw new FileException('Creating the directory failed.', 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readdir($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('readdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileException('Reading the directory failed.', 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('rmdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileException('Reading the directory failed.', 0, $exception);
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
            throw new FileException('Creating the directory failed.', 0, $exception);
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
            throw new FileException('Creating the directory failed.', 0, $exception);
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
            throw new FileException('Creating the directory failed.', 0, $exception);
        }
    }
}
