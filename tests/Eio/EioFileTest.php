<?php
namespace Icicle\Tests\File\Concurrent;

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
}