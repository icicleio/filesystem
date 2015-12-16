<?php
namespace Icicle\Tests\File\Concurrent;

use Icicle\Coroutine\Coroutine;
use Icicle\File\Concurrent\ConcurrentDriver;
use Icicle\Tests\File\AbstractFileTest;

class ConcurrentFileTest extends AbstractFileTest
{
    /**
     * @var \Icicle\File\Driver
     */
    protected function createDriver()
    {
        return new ConcurrentDriver();
    }

    protected function openFile($path, $mode = 'r+')
    {
        $coroutine = new Coroutine($this->getDriver()->open($path, $mode));

        return $coroutine->wait();
    }
}