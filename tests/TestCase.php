<?php
namespace Icicle\Tests\File;

use Icicle\Tests\File\Stub\CallbackStub;

/**
 * Abstract test class with methods for creating callbacks and asserting runtimes.
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    const RUNTIME_PRECISION = 2; // Number of decimals to use in runtime calculations/comparisons.
    const PATH = '/tmp/test.txt';
    const DIR = '/tmp/test';

    protected static $keys = [
        0  => 'dev',
        1  => 'ino',
        2  => 'mode',
        3  => 'nlink',
        4  => 'uid',
        5  => 'gid',
        6  => 'rdev',
        7  => 'size',
        8  => 'atime',
        9  => 'mtime',
        10 => 'ctime',
        11 => 'blksize',
        12 => 'blocks',
    ];

    public function tearDown()
    {
        if (file_exists(self::PATH)) {
            unlink(self::PATH);
        }

        if (is_dir(self::DIR)) {
            rmdir(self::DIR);
        }
    }

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

    /**
     * @param string $path
     *
     * @return string
     */
    public function getFileContents($path)
    {
        $handle = fopen($path, 'r');

        $data = fread($handle, 8192);

        fclose($handle);

        return $data;
    }

    /**
     * @param string $path
     * @param string $data
     */
    public function createFileWith($path, $data)
    {
        $handle = fopen($path, 'w');
        $written = fwrite($handle, $data);

        if (strlen($data) !== $written) {
            $this->fail('Could not write data to file.');
        }

        fclose($handle);
    }

    /**
     * @param string $path
     */
    public function createDirectory($path)
    {
        if (!mkdir($path)) {
            $this->fail('Could not create directory.');
        }
    }
}
