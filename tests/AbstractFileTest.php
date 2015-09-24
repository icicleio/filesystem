<?php
namespace Icicle\Tests\File;

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;

abstract class AbstractFileTest extends TestCase
{
    const PATH = 'test.txt';
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxz';


    /**
     * @param string $path
     * @param string $mode
     *
     * @return \Icicle\File\FileInterface
     */
    abstract protected function openFile($path, $mode = 'w+');

    public function tearDown()
    {
        if (file_exists(self::PATH)) {
            unlink(self::PATH);
        }
    }

    public function getFileContents($path)
    {
        $handle = fopen($path, 'r');

        $data = fread($handle, 8192);

        fclose($handle);

        return $data;
    }

    public function createFileWith($path, $data)
    {
        $handle = fopen($path, 'w');
        $written = fwrite($handle, $data);

        if (strlen($data) !== $written) {
            $this->fail('Could not write data to file.');
        }

        fclose($handle);
    }

    public function testIsOpen()
    {
        $file = $this->openFile(self::PATH, 'c');

        $this->assertTrue($file->isOpen());
    }

    /**
     * @depends testIsOpen
     */
    public function testRead()
    {
        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'r');

        $coroutine = new Coroutine($file->read());

        $data = $coroutine->wait();

        $this->assertSame(self::WRITE_STRING, $data);
    }

    /**
     * @depends testIsOpen
     */
    public function testReadTo()
    {
        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'r');

        $coroutine = new Coroutine($file->read(0, 'f'));

        $data = $coroutine->wait();

        $this->assertSame(substr(self::WRITE_STRING, 0, 6), $data);
    }

    /**
     * @depends testIsOpen
     */
    public function testWrite()
    {
        $file = $this->openFile(self::PATH);

        $coroutine = new Coroutine($file->write(self::WRITE_STRING));
        $written = $coroutine->wait();

        $this->assertSame(strlen(self::WRITE_STRING), $written);

        $this->assertSame(self::WRITE_STRING, $this->getFileContents(self::PATH));
    }

    /**
     * @depends testIsOpen
     */
    public function testEnd()
    {
        $file = $this->openFile(self::PATH);

        $coroutine = new Coroutine($file->end(self::WRITE_STRING));

        $this->assertFalse($file->isWritable());

        $written = $coroutine->wait();

        $this->assertSame(strlen(self::WRITE_STRING), $written);

        $this->assertSame(self::WRITE_STRING, $this->getFileContents(self::PATH));
    }
}
