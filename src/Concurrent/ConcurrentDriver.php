<?php
namespace Icicle\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker\DefaultQueue;
use Icicle\Concurrent\Worker\Queue;
use Icicle\File\Driver;
use Icicle\File\Exception\FileTaskException;

class ConcurrentDriver implements Driver
{
    /**
     * @var \Icicle\Concurrent\Worker\Queue
     */
    private $queue;

    /**
     * @param \Icicle\Concurrent\Worker\Queue|null $queue
     */
    public function __construct(Queue $queue = null)
    {
        $this->queue = $queue ?: new DefaultQueue();
        if (!$this->queue->isRunning()) {
            $this->queue->start();
        }
    }

    public function __destruct()
    {
        if ($this->queue->isRunning()) {
            $this->queue->kill();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($path, $mode)
    {
        $worker = $this->queue->pull();
        
        $task = new Internal\FileTask('fopen', [(string) $path, (string) $mode]);

        try {
            list($id, $size, $append) = (yield $worker->enqueue($task));
        } catch (TaskException $exception) {
            throw new FileTaskException('Opening the file failed.', $exception);
        }

        yield new ConcurrentFile($this->queue, $worker, $id, $path, $size, $append);
    }

    /**
     * {@inheritdoc}
     */
    public function unlink($path)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('unlink', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Unlinking the file failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stat($path)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('stat', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Stating the file failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename($oldPath, $newPath)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('rename', [(string) $oldPath, (string) $newPath]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Renaming the file failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isFile($path)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('isfile', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Determining if path is a file failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isDir($path)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('isdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Determine if the path is a directory failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function link($source, $target)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('link', [(string) $source, (string) $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the link failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function symlink($source, $target)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('symlink', [(string) $source, (string) $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the symlink failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readlink($path)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('readlink', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the symlink failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copy($source, $target)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('copy', [(string) $source, (string) $target]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Copying the file failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mkDir($path, $mode = 0755)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('mkdir', [(string) $path, (int) $mode]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lsDir($path)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('lsdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the directory failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rmDir($path)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('rmdir', [(string) $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Reading the directory failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($path, $mode)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('chmod', [(string) $path, (int) $mode]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chown($path, $uid)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('chown', [(string) $path, (int) $uid]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp($path, $gid)
    {
        $worker = $this->queue->pull();

        try {
            yield $worker->enqueue(new Internal\FileTask('chgrp', [(string) $path, (int) $gid]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Creating the directory failed.', $exception);
        } finally {
            $this->queue->push($worker);
        }
    }
}
