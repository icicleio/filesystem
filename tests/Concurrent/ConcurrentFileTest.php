<?php
namespace Icicle\Tests\File\Concurrent;

use Icicle\Concurrent\Worker\DefaultPool;
use Icicle\Coroutine\Coroutine;
use Icicle\File\Concurrent\ConcurrentDriver;
use Icicle\Tests\File\AbstractFileTest;

class ConcurrentFileTest extends AbstractFileTest
{
    /**
     * @var \Icicle\Concurrent\Worker\Pool
     */
    protected $pool;

    public function setUp()
    {
        $this->pool = new DefaultPool();
        parent::setUp();
    }

    public function tearDown()
    {
        if ($this->pool->isRunning()) {
            $this->pool->kill();
        }
        parent::tearDown();
    }

    /**
     * @var \Icicle\File\Driver
     */
    protected function createDriver()
    {
        return new ConcurrentDriver($this->pool);
    }

    protected function openFile($path, $mode = 'r+')
    {
        $coroutine = new Coroutine($this->getDriver()->open($path, $mode));

        return $coroutine->wait();
    }
}