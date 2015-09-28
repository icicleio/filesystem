<?php
namespace Icicle\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker\WorkerInterface;
use Icicle\Coroutine\Coroutine;
use Icicle\File\Exception\FileException;
use Icicle\File\FileInterface;
use Icicle\Stream\Exception\InvalidArgumentError;
use Icicle\Stream\Exception\OutOfBoundsException;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnseekableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\PipeTrait;

class ConcurrentFile implements FileInterface
{
    use PipeTrait;

    /**
     * @var \Icicle\Concurrent\Worker\WorkerInterface
     */
    private $worker;

    /**
     * @var string
     */
    private $path;

    /**
     * @var bool
     */
    private $open = true;

    /**
     * @var int
     */
    private $position;

    /**
     * @var bool
     */
    private $append = false;

    /**
     * @var bool
     */
    private $writable = true;

    /**
     * @var \SplQueue
     */
    private $queue;

    /**
     * @param \Icicle\Concurrent\Worker\WorkerInterface $worker
     * @param int $size
     * @param bool $append
     */
    public function __construct(WorkerInterface $worker, $path, $size, $append = false)
    {
        $this->worker = $worker;
        $this->path = $path;
        $this->size = $size;
        $this->append = $append;
        $this->position = $append ? $size : 0;

        $this->queue = new \SplQueue();
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen()
    {
        return $this->open;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->worker->isRunning()) {
            $coroutine = new Coroutine($this->worker->shutdown());
            $coroutine->done(null, function () {
                $this->worker->kill();
            });
        }

        while (!$this->queue->isEmpty()) {
            $promise = $this->queue->shift();
            $promise->cancel(new FileException('The file was closed.'));
        }

        $this->open = false;
        $this->writable = false;
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        return $this->position === $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length = 0, $byte = null, $timeout = 0)
    {
        if (!$this->isReadable()) {
            throw new UnreadableException('The file is no longer readable.');
        }

        $length = (int) $length;
        if (0 >= $length) {
            $length = self::CHUNK_SIZE;
        }

        try {
            $data = (yield $this->worker->enqueue(new Internal\FileTask('fread', [$length])));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Reading from the file failed.', 0, $exception);
        }

        $byte = (string) $byte;
        $byte = strlen($byte) ? $byte[0] : null;

        if (null !== $byte && false !== ($position = strpos($data, $byte))) {
            ++$position;
            $data = substr($data, 0, $position);
            yield $this->seek($this->position + $position);
        } else {
            $this->position += strlen($data);
        }

        yield $data;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return $this->isOpen() && !$this->eof();
    }

    /**
     * {@inheritdoc}
     */
    public function write($data, $timeout = 0)
    {
        return $this->send($data, false);
    }

    /**
     * {@inheritdoc}
     */
    public function end($data = '', $timeout = 0)
    {
        return $this->send($data, true);
    }

    /**
     * @coroutine
     *
     * @param string $data
     * @param bool $end
     *
     * @return \Generator
     *
     * @resolve int
     *
     * @throws \Icicle\File\Exception\FileException
     * @throws \Icicle\Stream\Exception\UnwritableException
     */
    protected function send($data, $end = false)
    {
        if (!$this->isWritable()) {
            throw new UnwritableException('The file is no longer writable.');
        }

        $task = new Internal\FileTask('fwrite', [(string) $data]);

        if ($this->queue->isEmpty()) {
            $promise = new Coroutine($this->worker->enqueue($task));
            $this->queue->push($promise);
        } else {
            $promise = $this->queue->top();
            $promise = $promise->then(function () use ($task) {
                return new Coroutine($this->worker->enqueue($task));
            });
        }

        if ($end) {
            $this->writable = false;
        }

        try {
            $written = (yield $promise);

            if ($this->append) {
                $this->size += $written;
            } else {
                $this->position += $written;
                if ($this->position > $this->size) {
                    $this->size = $this->position;
                }
            }
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Write to the file failed.', 0, $exception);
        } finally {
            if ($end) {
                $this->close();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET, $timeout = 0)
    {
        if (!$this->isOpen()) {
            throw new UnseekableException('The file is no longer seekable.');
        }

        switch ($whence) {
            case \SEEK_SET:
                break;

            case \SEEK_CUR:
                $offset = $this->position + $offset;
                break;

            case \SEEK_END:
                $offset = $this->size + $offset;
                break;

            default:
                throw new InvalidArgumentError('Invalid whence value. Use SEEK_SET, SEEK_CUR, or SEEK_END.');
        }

        if (0 > $offset) {
            throw new OutOfBoundsException(sprintf('Invalid offset: %s.', $offset));
        }

        $this->position = $offset;

        if ($this->position > $this->size) {
            $this->size = $this->position;
        }

        try {
            $this->position = (yield $this->worker->enqueue(
                new Internal\FileTask('fseek', [(int) $offset, \SEEK_SET])
            ));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Seeking in the file failed.', 0, $exception);
        }

        if ($this->position > $this->size) {
            $this->size = $this->position;
        }

        yield $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function getLength()
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function truncate($size)
    {
        if (!$this->isReadable() && !$this->isWritable()) {
            throw new UnseekableException('The file is no longer seekable.');
        }

        try {
            yield $this->worker->enqueue(new Internal\FileTask('ftruncate', [(int) $size]));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Seeking in the file failed.', 0, $exception);
        }

        $this->size = (int) $size;

        if ($this->position > $size) {
            $this->position = $size;
        }

        yield true;
    }

    /**
     * {@inheritdoc}
     */
    public function stat()
    {
        if (!$this->isOpen()) {
            throw new UnseekableException('The file has been closed.');
        }

        try {
            yield $this->worker->enqueue(new Internal\FileTask('fstat'));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Seeking in the file failed.', 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chown($uid)
    {
        return $this->change('fchown', $uid);
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp($group)
    {
        return $this->change('fchgrp', $group);
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($mode)
    {
        return $this->change('fchmod', $mode);
    }

    /**
     * @param string $operation
     * @param int $value
     *
     * @return \Generator
     *
     * @throws \Icicle\File\Exception\FileException
     */
    private function change($operation, $value)
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        try {
            yield $this->worker->enqueue(new Internal\FileTask($operation, [(int) $value]));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException(sprintf('Changing the file %s failed.', $operation), 0, $exception);
        }
    }
}
