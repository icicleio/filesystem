<?php
namespace Icicle\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker\Worker;
use Icicle\Coroutine\Coroutine;
use Icicle\Exception\InvalidArgumentError;
use Icicle\File\Exception\FileException;
use Icicle\File\File;
use Icicle\Stream\Exception\OutOfBoundsException;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnseekableException;
use Icicle\Stream\Exception\UnwritableException;

class ConcurrentFile implements File
{
    /**
     * @var \Icicle\Concurrent\Worker\Worker
     */
    private $worker;

    /**
     * @var int
     */
    private $id;

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
     * @var int
     */
    private $size;

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
     * @param \Icicle\Concurrent\Worker\Worker $worker
     * @param int $id
     * @param string $path
     * @param int $size
     * @param bool $append
     */
    public function __construct(Worker $worker, $id, $path, $size, $append = false)
    {
        $this->worker = $worker;
        $this->id = $id;
        $this->path = $path;
        $this->size = $size;
        $this->append = $append;
        $this->position = $append ? $size : 0;

        $this->queue = new \SplQueue();
    }

    public function __destruct()
    {
        if ($this->isOpen()) {
            $this->close();
        }
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
        if ($this->open && $this->worker->isRunning()) {
            $coroutine = new Coroutine($this->worker->enqueue(new Internal\FileTask('fclose', [], $this->id)));
            $coroutine->done(null, [$this->worker, 'kill']);
        }

        if (!$this->queue->isEmpty()) {
            $exception = new FileException('The file was closed.');
            do {
                $this->queue->shift()->cancel($exception);
            } while (!$this->queue->isEmpty());
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
        if (0 > $length) {
            throw new InvalidArgumentError('The length must be a non-negative integer.');
        }

        if (0 === $length) {
            $length = self::CHUNK_SIZE;
        }

        $awaitable = new Coroutine($this->worker->enqueue(new Internal\FileTask('fread', [$length], $this->id)));

        if ($timeout) {
            $awaitable = $awaitable->timeout($timeout);
        }

        try {
            $data = (yield $awaitable);
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Reading from the file failed.', $exception);
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
        return $this->send($data, $timeout, false);
    }

    /**
     * {@inheritdoc}
     */
    public function end($data = '', $timeout = 0)
    {
        return $this->send($data, $timeout, true);
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
    protected function send($data, $timeout, $end = false)
    {
        if (!$this->isWritable()) {
            throw new UnwritableException('The file is no longer writable.');
        }

        $task = new Internal\FileTask('fwrite', [(string) $data], $this->id);

        if ($this->queue->isEmpty()) {
            $awaitable = new Coroutine($this->worker->enqueue($task));
            $this->queue->push($awaitable);
        } else {
            $awaitable = $this->queue->top();
            $awaitable = $awaitable->then(function () use ($task) {
                return new Coroutine($this->worker->enqueue($task));
            });
        }

        if ($end) {
            $this->writable = false;
        }

        if ($timeout) {
            $awaitable = $awaitable->timeout($timeout);
        }

        try {
            $written = (yield $awaitable);

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
            throw new FileException('Write to the file failed.', $exception);
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

        $offset = (int) $offset;

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

        $awaitable = new Coroutine(
            $this->worker->enqueue(new Internal\FileTask('fseek', [$offset, \SEEK_SET], $this->id))
        );

        if ($timeout) {
            $awaitable = $awaitable->timeout($timeout);
        }

        try {
            $this->position = (yield $awaitable);
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Seeking in the file failed.', $exception);
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
            throw new FileException('The file is no longer seekable.');
        }

        $size = (int) $size;

        if (0 > $size) {
            throw new InvalidArgumentError('The size must be a non-negative integer.');
        }

        try {
            yield $this->worker->enqueue(new Internal\FileTask('ftruncate', [$size], $this->id));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Truncating the file failed.', $exception);
        }

        $this->size = $size;

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
            throw new FileException('The file has been closed.');
        }

        try {
            yield $this->worker->enqueue(new Internal\FileTask('fstat', [], $this->id));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException('Stating file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path)
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        try {
            yield $this->worker->enqueue(new Internal\FileTask('copy', [$this->path, (string) $path]));
        } catch (TaskException $exception) {
            throw new FileException('Copying the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chown($uid)
    {
        return $this->change('chown', $uid);
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp($group)
    {
        return $this->change('chgrp', $group);
    }

    /**
     * {@inheritdoc}
     */
    public function chmod($mode)
    {
        return $this->change('chmod', $mode);
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
            yield $this->worker->enqueue(new Internal\FileTask($operation, [$this->path, (int) $value]));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileException(sprintf('%s failed.', $operation), $exception);
        }
    }
}
