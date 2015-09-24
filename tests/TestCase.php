<?php
namespace Icicle\Tests\File;

use Icicle\Tests\File\Stub\CallbackStub;

/**
 * Abstract test class with methods for creating callbacks and asserting runtimes.
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    const RUNTIME_PRECISION = 2; // Number of decimals to use in runtime calculations/comparisons.
    
    /**
     * Creates a callback that must be called $count times or the test will fail.
     *
     * @param int $count Number of times the callback should be called.
     *
     * @return callable|\PHPUnit_Framework_MockObject_MockObject Object that is callable and expects to be called the
     *     given number of times.
     */
    public function createCallback($count)
    {
        $mock = $this->getMock(CallbackStub::class);
        
        $mock->expects($this->exactly($count))
            ->method('__invoke');

        return $mock;
    }
}
