<?php
namespace Icicle\File\Concurrent\Internal;

use Icicle\Concurrent\Worker\WorkerFactory;

class WorkerQueue
{
    const DEFAULT_MAX = 8;

    /**
     * @var \Icicle\Concurrent\Worker\WorkerFactory
     */
    private $factory;

    /**
     * @var int
     */
    private $max;

    /**
     * @var \SplQueue
     */
    private $queue;

    /**
     * @param \Icicle\Concurrent\Worker\WorkerFactory $factory
     * @param int $maxWorkers
     */
    public function __construct(WorkerFactory $factory, $maxWorkers = self::DEFAULT_MAX)
    {
        $this->factory = $factory;
        $this->max = $maxWorkers;
        $this->queue = new \SplQueue();
    }

    /**
     * @return \Icicle\Concurrent\Worker\Worker
     */
    public function pull()
    {
        do {
            if ($this->queue->count() < $this->max) {
                $worker = $this->factory->create();
                $worker->start();
            } else {
                $worker = $this->queue->shift();
            }
        } while (!$worker->isRunning());

        $this->queue->push($worker);

        return $worker;
    }
}
