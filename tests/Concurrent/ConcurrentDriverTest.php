<?php
namespace Icicle\Tests\File\Concurrent;

use Icicle\File\Concurrent\ConcurrentDriver;
use Icicle\Tests\File\AbstractDriverTest;

class ConcurrentDriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return new ConcurrentDriver();
    }
}