<?php
namespace Icicle\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker\Worker;
use Icicle\Coroutine\Coroutine;
use Icicle\Exception\InvalidArgumentError;
use Icicle\File\Exception\FileException;
use Icicle\File\Exception\FileTaskException;
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
    public function __construct(Worker $worker, int $id, string $path, int $size, bool $append = false)
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
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->open && $this->worker->isRunning()) {
            $coroutine = new Coroutine($this->worker->enqueue(new Internal\FileTask('fclose', [$this->id])));
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
    public function eof(): bool
    {
        return $this->position === $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length = 0, string $byte = null, float $timeout = 0): \Generator
    {
        if (!$this->isReadable()) {
            throw new UnreadableException('The file is no longer readable.');
        }

        if (0 > $length) {
            throw new InvalidArgumentError('The length must be a non-negative integer.');
        }

        if (0 === $length) {
            $length = self::CHUNK_SIZE;
        }

        $awaitable = new Coroutine($this->worker->enqueue(new Internal\FileTask('fread', [$this->id, $length])));

        if ($timeout) {
            $awaitable = $awaitable->timeout($timeout);
        }

        try {
            $data = yield $awaitable;
        } catch (TaskException $exception) {
            $this->close();
            throw new FileTaskException('Reading from the file failed.', $exception);
        }

        $byte = strlen($byte) ? $byte[0] : null;

        if (null !== $byte && false !== ($position = strpos($data, $byte))) {
            ++$position;
            $data = substr($data, 0, $position);
            yield from $this->seek($this->position + $position);
        } else {
            $this->position += strlen($data);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        return $this->isOpen() && !$this->eof();
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data, float $timeout = 0): \Generator
    {
        return $this->send($data, $timeout, false);
    }

    /**
     * {@inheritdoc}
     */
    public function end(string $data = '', float $timeout = 0): \Generator
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
    protected function send(string $data, float $timeout, bool $end = false): \Generator
    {
        if (!$this->isWritable()) {
            throw new UnwritableException('The file is no longer writable.');
        }

        $task = new Internal\FileTask('fwrite', [$this->id, (string) $data]);

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
            $written = yield $awaitable;

            if ($this->append) {
                $this->size += $written;
            } else {
                $this->position += $written;
                if ($this->position > $this->size) {
                    $this->size = $this->position;
                }
            }

            return $written;
        } catch (TaskException $exception) {
            $this->close();
            throw new FileTaskException('Write to the file failed.', $exception);
        } finally {
            if ($end) {
                $this->close();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = SEEK_SET, float $timeout = 0): \Generator
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
            $this->position = yield from $this->worker->enqueue(
                new Internal\FileTask('fseek', [$this->id, $offset, \SEEK_SET])
            );
        } catch (TaskException $exception) {
            $this->close();
            throw new FileTaskException('Seeking in the file failed.', $exception);
        }

        if ($this->position > $this->size) {
            $this->size = $this->position;
        }

        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function getLength(): int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function truncate(int $size): \Generator
    {
        if (!$this->isReadable() && !$this->isWritable()) {
            throw new FileException('The file is no longer seekable.');
        }

        if (0 > $size) {
            throw new InvalidArgumentError('The size must be a non-negative integer.');
        }

        try {
            yield from $this->worker->enqueue(new Internal\FileTask('ftruncate', [$this->id, $size]));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileTaskException('Truncating the file failed.', $exception);
        }

        $this->size = $size;

        if ($this->position > $size) {
            $this->position = $size;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stat(): \Generator
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        try {
            return yield from $this->worker->enqueue(new Internal\FileTask('fstat', [$this->id]));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileTaskException('Stating file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $path): \Generator
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        try {
            return yield from $this->worker->enqueue(new Internal\FileTask('copy', [$this->path, $path]));
        } catch (TaskException $exception) {
            throw new FileTaskException('Copying the file failed.', $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chown(int $uid): \Generator
    {
        return $this->change('chown', $uid);
    }

    /**
     * {@inheritdoc}
     */
    public function chgrp(int $group): \Generator
    {
        return $this->change('chgrp', $group);
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(int $mode): \Generator
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
    private function change(string $operation, int $value): \Generator
    {
        if (!$this->isOpen()) {
            throw new FileException('The file has been closed.');
        }

        try {
            return yield from $this->worker->enqueue(new Internal\FileTask($operation, [$this->path, $value]));
        } catch (TaskException $exception) {
            $this->close();
            throw new FileTaskException(sprintf('%s failed.', $operation), $exception);
        }
    }
}
