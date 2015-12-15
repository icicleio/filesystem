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
     * @var \Icicle\File\Driver
     */
    protected static $driver;

    public static function setUpBeforeClass()
    {
        self::$driver = new EioDriver();
    }

    protected function openFile($path, $mode = 'r+')
    {
        $coroutine = new Coroutine(self::$driver->open($path, $mode));

        return $coroutine->wait();
    }
}