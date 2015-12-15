<?php
namespace Icicle\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker;
use Icicle\Concurrent\Worker\DefaultWorkerFactory;
use Icicle\Concurrent\Worker\Pool;
use Icicle\Concurrent\Worker\WorkerFactory;
use Icicle\File\Driver;
use Icicle\File\Exception\FileTaskException;

class ConcurrentDriver implements Driver
{
    /**
     * @var \Icicle\Concurrent\Worker\WorkerFactory
     */
    private $factory;

    /**
     * @var \Icicle\Concurrent\Worker\Pool
     */
    private $pool;

    /**
     * @param \Icicle\Concurrent\Worker\WorkerFactory|null $factory
     * @param \Icicle\Concurrent\Worker\Pool|null $pool
     */
    public function __construct(WorkerFactory $factory = null, Pool $pool = null)
    {
        $this->factory = $factory ?: new DefaultWorkerFactory();
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
            throw new FileTaskException('Opening the file failed.', $exception);
        }

        yield new ConcurrentFile($worker, $path, $size, $append);
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
    public function isfile($path)
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
    public function isdir($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('isdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Determine if the path is a directory failed.', $exception);
        }
    }

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
    public function mkdir($path, $mode = 0755)
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
    public function lsdir($path)
    {
        try {
            yield $this->pool->enqueue(new Internal\FileTask('lsdirf', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the directory failed.', $exception);
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
