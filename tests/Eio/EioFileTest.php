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
    protected function createDriver()
    {
        return new EioDriver();
    }

    protected function openFile($path, $mode = 'r+')
    {
        $coroutine = new Coroutine($this->getDriver()->open($path, $mode));

        return $coroutine->wait();
    }
}