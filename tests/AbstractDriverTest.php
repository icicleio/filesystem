<?php
namespace Icicle\Tests\File;

use Icicle\Coroutine\Coroutine;
use Icicle\File\File;
use Icicle\Loop;

abstract class AbstractDriverTest extends TestCase
{
    /**
     * @var \Icicle\File\Driver
     */
    protected $driver;

    /**
     * @return \Icicle\File\Driver
     */
    abstract protected function createDriver();

    public function setUp()
    {
        $this->driver = $this->createDriver();
    }

    public function testOpen()
    {
        $coroutine = new Coroutine($this->driver->open(self::PATH, 'c+'));
        $file = $coroutine->wait();

        $this->assertInstanceOf(File::class, $file);
        $this->assertTrue($file->isOpen());
    }

    /**
     * @depends testOpen
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testOpenWithInvalidMode()
    {
        $coroutine = new Coroutine($this->driver->open(self::PATH, 'o'));
        $coroutine->wait();
    }

    /**
     * @depends testOpen
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testOpenOnNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->open(self::PATH, 'r'));
        $coroutine->wait();
    }

    /**
     * @depends testOpen
     */
    public function testOpenWithTruncate()
    {
        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine($this->driver->open(self::PATH, 'w+'));
        $file = $coroutine->wait();

        $this->assertSame($file->getLength(), 0);
        $file->close();

        $this->assertSame('', $this->getFileContents(self::PATH));
    }

    public function testUnlink()
    {
        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine($this->driver->unlink(self::PATH));
        $this->assertTrue($coroutine->wait());
        $this->assertFalse(file_exists(self::PATH));
    }

    /**
     * @depends testUnlink
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testUnlinkNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->unlink(self::PATH));
        $coroutine->wait();
    }

    /**
     * @depends testUnlink
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testUnlinkDirectory()
    {
        $this->createDirectory(self::DIR);

        $coroutine = new Coroutine($this->driver->unlink(self::DIR));
        $coroutine->wait();
    }

    public function testRename()
    {
        $path = self::PATH . '.backup';

        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine($this->driver->rename(self::PATH, $path));
        $this->assertTrue($coroutine->wait());

        $this->assertTrue(file_exists($path));

        unlink($path);

        $this->assertFalse(file_exists(self::PATH));
    }

    /**
     * @depends testRename
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testRenameNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->rename(self::PATH, self::PATH . '.backup'));
        $coroutine->wait();
    }

    public function testStat()
    {
        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine($this->driver->stat(self::PATH));
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
     * @depends testStat
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testStatNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->stat(self::PATH));
        $coroutine->wait();
    }

    public function testIsFile()
    {
        $coroutine = new Coroutine($this->driver->isFile(self::PATH));
        $this->assertFalse($coroutine->wait());

        $this->createFileWith(self::PATH, 'testing');
        $coroutine = new Coroutine($this->driver->isFile(self::PATH));
        $this->assertTrue($coroutine->wait());

        $this->createDirectory(self::DIR);
        $coroutine = new Coroutine($this->driver->isFile(self::DIR));
        $this->assertFalse($coroutine->wait());
    }

    public function testIsDir()
    {
        $coroutine = new Coroutine($this->driver->isDir(self::DIR));
        $this->assertFalse($coroutine->wait());

        $this->createDirectory(self::DIR);
        $coroutine = new Coroutine($this->driver->isDir(self::DIR));
        $this->assertTrue($coroutine->wait());

        $this->createFileWith(self::PATH, 'testing');
        $coroutine = new Coroutine($this->driver->isDir(self::PATH));
        $this->assertFalse($coroutine->wait());
    }

    public function testMkDir()
    {
        $coroutine = new Coroutine($this->driver->mkDir(self::DIR));
        $this->assertTrue($coroutine->wait());
        $this->assertTrue(is_dir(self::DIR));
    }

    /**
     * @depends testMkDir
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testMkDirWhenOneAlreadyExists()
    {
        $this->createDirectory(self::DIR);
        $coroutine = new Coroutine($this->driver->mkDir(self::DIR));
        $coroutine->wait();
    }

    public function testLsDir()
    {
        $path1 = self::DIR . '/test1.txt';
        $path2 = self::DIR . '/test2.txt';

        $this->createDirectory(self::DIR);
        $this->createFileWith($path1, 'testing1');
        $this->createFileWith($path2, 'testing2');

        try {
            $coroutine = new Coroutine($this->driver->lsDir(self::DIR));
            $ls = $coroutine->wait();

            $this->assertSame($ls, ['test1.txt', 'test2.txt']);
        } finally {
            unlink($path1);
            unlink($path2);
            rmdir(self::DIR);
        }
    }

    /**
     * @depends testLsDir
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testLsNonExistentDirectory()
    {
        $coroutine = new Coroutine($this->driver->lsDir(self::DIR));
        $coroutine->wait();
    }

    public function testRmDir()
    {
        $this->createDirectory(self::DIR);
        $coroutine = new Coroutine($this->driver->rmDir(self::DIR));
        $this->assertTrue($coroutine->wait());
        $this->assertFalse(is_dir(self::DIR));
    }

    /**
     * @depends testRmDir
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testRmNonExistentDirectory()
    {
        $coroutine = new Coroutine($this->driver->rmDir(self::DIR));
        $coroutine->wait();
    }

    public function testCopy()
    {
        $content = 'testing';
        $this->createFileWith(self::PATH, $content);
        $path = self::PATH . '.backup';

        $coroutine = new Coroutine($this->driver->copy(self::PATH, $path));
        $written = $coroutine->wait();

        $this->assertTrue(file_exists($path));

        try {
            $this->assertSame(strlen($content), $written);
            $this->assertSame($content, $this->getFileContents($path));
        } finally {
            unlink($path);
        }
    }

    /**
     * @depends testCopy
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testCopyNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->copy(self::PATH, self::PATH . '.backup'));
        $coroutine->wait();
    }

    public function testLink()
    {
        $content = 'testing';
        $this->createFileWith(self::PATH, $content);
        $path = self::PATH . '.link';

        $coroutine = new Coroutine($this->driver->link(self::PATH, $path));
        $this->assertTrue($coroutine->wait());

        $this->assertTrue(file_exists($path));

        try {
            $this->assertSame($content, $this->getFileContents($path));

            $stat = stat(self::PATH);
            $this->assertSame(2, $stat['nlink']);
        } finally {
            unlink($path);
        }
    }

    /**
     * @depends testLink
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testLinkNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->link(self::PATH, self::PATH . '.link'));
        $coroutine->wait();
    }

    public function testSymlink()
    {
        $content = 'testing';
        $this->createFileWith(self::PATH, $content);
        $path = self::PATH . '.symlink';

        $coroutine = new Coroutine($this->driver->symlink(self::PATH, $path));
        $this->assertTrue($coroutine->wait());

        $this->assertTrue(file_exists($path));

        try {
            $this->assertSame($content, $this->getFileContents($path));

            $stat = stat(self::PATH);
            $this->assertSame(1, $stat['nlink']);
        } finally {
            unlink($path);
        }
    }

    /**
     * @depends testSymlink
     */
    public function testReadlink()
    {
        $this->createFileWith(self::PATH, 'testing');
        $path = self::PATH . '.symlink';

        $coroutine = new Coroutine($this->driver->symlink(self::PATH, $path));
        $this->assertTrue($coroutine->wait());

        try {
            $coroutine = new Coroutine($this->driver->readlink($path));
            $this->assertSame(self::PATH, $coroutine->wait());
        } finally {
            unlink($path);
        }
    }

    /**
     * @depends testReadlink
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testReadlinkNonExistentSymlink()
    {
        $coroutine = new Coroutine($this->driver->readlink(self::PATH));
        $coroutine->wait();
    }

    /**
     * @depends testReadlink
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testReadlinkNonSymlink()
    {
        $this->createFileWith(self::PATH, 'testing');
        $coroutine = new Coroutine($this->driver->readlink(self::PATH));
        $coroutine->wait();
    }

    public function testChmod()
    {
        $mode = 0777;

        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine($this->driver->chmod(self::PATH, $mode));
        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($mode, $stat['mode'] & 0777);
    }

    /**
     * @depends testChmod
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testChmodOnNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->chmod(self::PATH, 0777));
        $coroutine->wait();
    }

    public function testChown()
    {
        $uid = getmyuid();

        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine($this->driver->chown(self::PATH, $uid));
        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($uid, $stat['uid']);
    }

    /**
     * @depends testChown
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testChownOnNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->chown(self::PATH, getmyuid()));
        $coroutine->wait();
    }

    public function testChgrp()
    {
        $gid = getmygid();

        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine($this->driver->chgrp(self::PATH, $gid));
        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($gid, $stat['gid']);
    }

    /**
     * @depends testChgrp
     * @expectedException \Icicle\File\Exception\FileException
     */
    public function testChgrpOnNonExistentFile()
    {
        $coroutine = new Coroutine($this->driver->chgrp(self::PATH, getmygid()));
        $coroutine->wait();
    }
}
