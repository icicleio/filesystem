<?php
namespace Icicle\Tests\File\Concurrent;

use Icicle\Concurrent\Exception\TaskException;
use Icicle\Concurrent\Worker\BasicEnvironment;
use Icicle\Concurrent\Worker\Pool;
use Icicle\Concurrent\Worker\Task;
use Icicle\Concurrent\Worker\Worker;
use Icicle\File\Concurrent\ConcurrentDriver;
use Icicle\Tests\File\AbstractDriverTest;

class MockedDriverTest extends AbstractDriverTest
{
    /**
     * @return \Icicle\File\Driver
     */
    protected function createDriver()
    {
        $environment = new BasicEnvironment();
        $enqueue = function (Task $task) use ($environment) {
            try {
                return yield $task->run($environment);
            } catch (\Exception $exception) {
                throw new TaskException('Task failed.', 0, $exception);
            }
        };

        $pool = $this->getMock(Pool::class);

        $pool->method('isRunning')
            ->will($this->returnValue(true));

        $pool->method('enqueue')
            ->will($this->returnCallback($enqueue));

        $worker = $this->getMock(Worker::class);

        $worker->method('isRunning')
            ->will($this->returnValue(true));

        $worker->method('enqueue')
            ->will($this->returnCallback($enqueue));

        $pool->method('get')
            ->will($this->returnValue($worker));

        return new ConcurrentDriver($pool);
    }
}