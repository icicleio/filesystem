<?php
namespace Icicle\Tests\File\Concurrent;

use Icicle\Coroutine\Coroutine;
use Icicle\File\Eio\EioDriver;
use Icicle\Tests\File\AbstractFileTest;

/**
 * @requires extension eio
 */
class EioFileTest extends AbstractFileTest
{
    /**
     * @var \Icicle\File\Concurrent\ConcurrentDriver
     */
    protected $driver;

    public function setUp()
    {
        $this->driver = new EioDriver();
    }

    protected function openFile($path, $mode = 'r+')
    {
        $coroutine = new Coroutine($this->driver->open($path, $mode));

        return $coroutine->wait();
    }
}