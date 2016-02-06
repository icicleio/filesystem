<?php
namespace Icicle\Tests\File;

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;

abstract class AbstractFileTest extends TestCase
{
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxz';

    /**
     * @var \Icicle\File\Driver
     */
    protected $driver;

    /**
     * @return \Icicle\File\Driver
     */
    abstract protected function createDriver();

    /**
     * @param string $path
     * @param string $mode
     *
     * @return \Icicle\File\File
     */
    protected function openFile($path, $mode = 'r+')
    {
        $coroutine = new Coroutine($this->getDriver()->open($path, $mode));

        return $coroutine->wait();
    }

    public function setUp()
    {
        $this->driver = $this->createDriver();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->driver = null;
    }

    /**
     * @return \Icicle\File\Driver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    public function testIsOpen()
    {
        $file = $this->openFile(self::PATH, 'c+');

        $this->assertTrue($file->isOpen());
    }

    public function testEof()
    {
        $file = $this->openFile(self::PATH, 'c+');

        $this->assertTrue($file->eof());
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
     * @depends testRead
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testReadOnWriteOnlyFile()
    {
        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'a');

        $coroutine = new Coroutine($file->seek(0));

        $position = $coroutine->wait();

        $coroutine = new Coroutine($file->read());

        $data = $coroutine->wait();
    }

    /**
     * @depends testEof
     * @expectedException \Icicle\Stream\Exception\UnreadableException
     */
    public function testReadAtEof()
    {
        $file = $this->openFile(self::PATH, 'w+');

        $this->assertTrue($file->eof());

        $coroutine = new Coroutine($file->read());

        $data = $coroutine->wait();
    }

    /**
     * @depends testIsOpen
     */
    public function testWrite()
    {
        $file = $this->openFile(self::PATH, 'w');

        $coroutine = new Coroutine($file->write(self::WRITE_STRING));
        $written = $coroutine->wait();

        $this->assertSame(strlen(self::WRITE_STRING), $written);

        $this->assertSame(self::WRITE_STRING, $this->getFileContents(self::PATH));
    }

    /**
     * @depends testWrite
     */
    public function testEnd()
    {
        $file = $this->openFile(self::PATH, 'w');

        $coroutine = new Coroutine($file->end(self::WRITE_STRING));

        $this->assertFalse($file->isWritable());

        $written = $coroutine->wait();

        $this->assertSame(strlen(self::WRITE_STRING), $written);

        $this->assertSame(self::WRITE_STRING, $this->getFileContents(self::PATH));
    }

    /**
     * @depends testWrite
     */
    public function testSimultaneousWrite()
    {
        $file = $this->openFile(self::PATH, 'w');

        $callback = $this->createCallback(2);
        $callback->method('__invoke')
            ->with($this->identicalTo(strlen(self::WRITE_STRING)));

        $coroutine = new Coroutine($file->write(self::WRITE_STRING));
        $coroutine->done($callback);

        $coroutine = new Coroutine($file->write(self::WRITE_STRING));
        $coroutine->done($callback);

        Loop\run();

        $file->close();

        $this->assertSame(self::WRITE_STRING . self::WRITE_STRING, $this->getFileContents(self::PATH));
    }

    /**
     * @depends testWrite
     * @expectedException \Icicle\Awaitable\Exception\CancelledException
     */
    public function testWriteThenCancel()
    {
        $file = $this->openFile(self::PATH, 'w');

        $coroutine = new Coroutine($file->write(self::WRITE_STRING));

        $coroutine->cancel();

        $written = $coroutine->wait();
    }

    /**
     * @depends testWrite
     * @expectedException \Icicle\Stream\Exception\UnwritableException
     */
    public function testWriteAfterClose()
    {
        $file = $this->openFile(self::PATH, 'w');

        $file->close();

        $coroutine = new Coroutine($file->write(self::WRITE_STRING));

        $written = $coroutine->wait();
    }

    /**
     * @depends testWrite
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testWriteOnReadOnlyFile()
    {
        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'r');

        $coroutine = new Coroutine($file->write(self::WRITE_STRING));

        $data = $coroutine->wait();
    }

    /**
     * @depends testRead
     */
    public function testSeek()
    {
        $position = 6;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH);

        $coroutine = new Coroutine($file->seek($position));

        $this->assertSame($position, $coroutine->wait());
        $this->assertSame($position, $file->tell());

        $coroutine = new Coroutine($file->read());

        $this->assertSame(substr(self::WRITE_STRING, $position), $coroutine->wait());
    }

    /**
     * @depends testSeek
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testSeekInvalidWhence()
    {
        $file = $this->openFile(self::PATH, 'w+');

        $coroutine = new Coroutine($file->seek(0, -1));

        $position = $coroutine->wait();
    }

    /**
     * @depends testSeek
     * @expectedException \Icicle\Stream\Exception\UnseekableException
     */
    public function testSeekAfterClose()
    {
        $file = $this->openFile(self::PATH, 'w+');

        $file->close();

        $coroutine = new Coroutine($file->seek(0));

        $position = $coroutine->wait();
    }

    /**
     * @depends testSeek
     */
    public function testSeekFromCurrent()
    {
        $position = -10;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'a+');

        $coroutine = new Coroutine($file->seek($position, \SEEK_CUR));

        $tell = strlen(self::WRITE_STRING) + $position;

        $this->assertSame($tell, $coroutine->wait());
        $this->assertSame($tell, $file->tell());

        $coroutine = new Coroutine($file->read());

        $this->assertSame(substr(self::WRITE_STRING, $position), $coroutine->wait());
    }

    /**
     * @depends testSeek
     * @expectedException \Icicle\Stream\Exception\OutOfBoundsException
     */
    public function testSeekInvalidPosition()
    {
        $position = -1;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH);

        $coroutine = new Coroutine($file->seek($position));

        $coroutine->wait();
    }

    /**
     * @depends testSeek
     */
    public function testSeekFromEndOfFile()
    {
        $position = -10;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH);

        $coroutine = new Coroutine($file->seek($position, \SEEK_END));

        $tell = strlen(self::WRITE_STRING) + $position;

        $this->assertSame($tell, $coroutine->wait());
        $this->assertSame($tell, $file->tell());

        $coroutine = new Coroutine($file->read());

        $this->assertSame(substr(self::WRITE_STRING, $position), $coroutine->wait());
    }

    /**
     * @depends testWrite
     * @depends testSeek
     */
    public function testSeekPastEndOfFile()
    {
        $position = 1;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH);

        $coroutine = new Coroutine($file->seek($position, \SEEK_END));

        $tell = strlen(self::WRITE_STRING) + $position;

        $this->assertSame($tell, $coroutine->wait());
        $this->assertSame($tell, $file->tell());
        $this->assertSame($tell, $file->getLength());

        $coroutine = new Coroutine($file->write('!'));

        $this->assertSame(1, $coroutine->wait());

        $this->assertSame(self::WRITE_STRING . "\0!", $this->getFileContents(self::PATH));
    }

    /**
     * @depends testRead
     */
    public function testTruncate()
    {
        $position = 10;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'r+');

        $coroutine = new Coroutine($file->truncate($position));

        $this->assertTrue($coroutine->wait());

        $this->assertSame($position, $file->getLength());

        $coroutine = new Coroutine($file->read());

        $this->assertSame(substr(self::WRITE_STRING, 0, $position), $coroutine->wait());
    }

    /**
     * @depends testTruncate
     * @depends testSeek
     */
    public function testTruncateBelowPosition()
    {
        $position = 10;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'r+');

        $coroutine = new Coroutine($file->seek($position + 1));

        $this->assertSame($position + 1, $coroutine->wait());

        $coroutine = new Coroutine($file->truncate($position));

        $this->assertTrue($coroutine->wait());

        $this->assertSame($position, $file->tell());
        $this->assertSame($position, $file->getLength());
    }

    /**
     * @depends testTruncate
     */
    public function testTruncatePastSize()
    {
        $position = strlen(self::WRITE_STRING) + 1;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'r+');

        $coroutine = new Coroutine($file->truncate($position));

        $this->assertTrue($coroutine->wait());

        $this->assertSame($position, $file->getLength());

        $this->assertSame(self::WRITE_STRING . "\0", $this->getFileContents(self::PATH));
    }

    /**
     * @depends testTruncate
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testTruncateReadOnly()
    {
        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH, 'r');

        $coroutine = new Coroutine($file->truncate(0));

        $result = $coroutine->wait();
    }

    public function testStat()
    {
        $file = $this->openFile(self::PATH, 'w+');

        $coroutine = new Coroutine($file->stat());

        $stat = $coroutine->wait();

        $this->assertInternalType('array', $stat);

        foreach (self::$keys as $key) {
            $this->assertArrayHasKey($key, $stat);
        }

        foreach (range(0, 12) as $key) {
            $this->assertArrayHasKey($key, $stat);
        }
    }

    /**
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testStatAfterClose()
    {
        $file = $this->openFile(self::PATH, 'w+');

        $file->close();

        $coroutine = new Coroutine($file->stat());
        $coroutine->wait();
    }

    public function testChmod()
    {
        $mode = 0777;

        $file = $this->openFile(self::PATH, 'w+');

        $coroutine = new Coroutine($file->chmod($mode));

        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($mode, $stat['mode'] & 0777);
    }

    /**
     * @depends testChmod
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testChmodAfterClose()
    {
        $file = $this->openFile(self::PATH, 'w+');

        $file->close();

        $coroutine = new Coroutine($file->chmod(0777));
        $coroutine->wait();
    }

    public function testChown()
    {
        $uid = getmyuid();

        $file = $this->openFile(self::PATH, 'w+');

        $coroutine = new Coroutine($file->chown($uid));

        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($uid, $stat['uid']);
    }

    /**
     * @depends testChown
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testChownAfterClose()
    {
        $file = $this->openFile(self::PATH, 'w+');

        $file->close();

        $coroutine = new Coroutine($file->chown(getmypid()));
        $coroutine->wait();
    }

    public function testChgrp()
    {
        $gid = getmygid();

        $file = $this->openFile(self::PATH, 'w+');

        $coroutine = new Coroutine($file->chgrp($gid));

        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($gid, $stat['gid']);
    }

    /**
     * @depends testChgrp
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testChgrpAfterClose()
    {
        $file = $this->openFile(self::PATH, 'w+');

        $file->close();

        $coroutine = new Coroutine($file->chgrp(getmygid()));
        $coroutine->wait();
    }

    public function testCopy()
    {
        $path = self::PATH . '.copy';

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH);

        $coroutine = new Coroutine($file->copy($path));

        try {
            $this->assertSame(strlen(self::WRITE_STRING), $coroutine->wait());
            $this->assertTrue(is_file($path));
        } finally {
            @unlink($path);
        }
    }

    /**
     * @depends testCopy
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testCopyAfterClose()
    {
        $path = self::PATH . '.copy';

        $file = $this->openFile(self::PATH, 'w+');

        $file->close();

        $coroutine = new Coroutine($file->copy($path));
        $coroutine->wait();
    }

    public function testRename()
    {
        $path = self::PATH . '.rename';

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $file = $this->openFile(self::PATH);

        $coroutine = new Coroutine($file->rename($path));

        try {
            $this->assertTrue($coroutine->wait());
            $this->assertSame($path, $file->getPath());
            $this->assertTrue(is_file($path));
            $this->assertFalse(is_file(self::PATH));
        } finally {
            @unlink($path);
        }
    }

    /**
     * @depends testCopy
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testRenameAfterClose()
    {
        $path = self::PATH . '.rename';

        $file = $this->openFile(self::PATH, 'w+');

        $file->close();

        $coroutine = new Coroutine($file->rename($path));
        $coroutine->wait();
    }
}
