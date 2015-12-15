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
    protected static $driver;

    public static function setUpBeforeClass()
    {
        self::$driver = new ConcurrentDriver();
    }

    protected function openFile($path, $mode = 'r+')
    {
        $coroutine = new Coroutine(self::$driver->open($path, $mode));

        return $coroutine->wait();
    }
}