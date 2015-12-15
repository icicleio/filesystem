<?php
namespace Icicle\Tests\File\Concurrent;

use Icicle\File\Eio\EioDriver;
use Icicle\Tests\File\AbstractDriverTest;

/**
 * @requires extension eio
 */
class EioDriverTest extends AbstractDriverTest
{
    protected function createDriver()
    {
        return new EioDriver();
    }
}