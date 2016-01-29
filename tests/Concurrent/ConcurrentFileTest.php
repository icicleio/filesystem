<?php
namespace Icicle\Tests\File\Concurrent;

use Icicle\Concurrent\Worker\DefaultPool;
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
        $this->pool = $this->createPool();
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
     * @return \Icicle\Concurrent\Worker\DefaultPool
     */
    public function createPool()
    {
        return new DefaultPool();
    }

    /**
     * @return \Icicle\File\Driver
     */
    protected function createDriver()
    {
        return new ConcurrentDriver($this->pool);
    }
}