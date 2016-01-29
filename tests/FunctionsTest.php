<?php
namespace Icicle\Tests\File;

use Icicle\Coroutine\Coroutine;
use Icicle\File;
use Icicle\File\File as AsyncFile;
use Icicle\Loop;

class FunctionsTest extends TestCase
{
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxz';

    public function testOpen()
    {
        $coroutine = new Coroutine(File\open(self::PATH, 'w+'));

        $this->assertInstanceOf(AsyncFile::class, $coroutine->wait());
    }

    /**
     * @depends testOpen
     */
    public function testGet()
    {
        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $coroutine = new Coroutine(File\get(self::PATH));

        $this->assertSame(self::WRITE_STRING, $coroutine->wait());
    }

    /**
     * @depends testOpen
     */
    public function testPut()
    {
        $coroutine = new Coroutine(File\put(self::PATH, self::WRITE_STRING, false));

        $this->assertSame(strlen(self::WRITE_STRING), $coroutine->wait());
        $this->assertSame(self::WRITE_STRING, $this->getFileContents(self::PATH));

        $coroutine = new Coroutine(File\put(self::PATH, self::WRITE_STRING, true));

        $this->assertSame(strlen(self::WRITE_STRING), $coroutine->wait());
        $this->assertSame(self::WRITE_STRING . self::WRITE_STRING, $this->getFileContents(self::PATH));
    }

    public function testUnlink()
    {
        $this->createFileWith(self::PATH, '');

        $coroutine = new Coroutine(File\unlink(self::PATH));

        $this->assertTrue($coroutine->wait());
        $this->assertFalse(is_file(self::PATH));
    }

    public function testStat()
    {
        $this->createFileWith(self::PATH, '');

        $coroutine = new Coroutine(File\stat(self::PATH));

        $result = $coroutine->wait();

        $this->assertInternalType('array', $result);
        $this->assertSame(0, $result['size']);
    }

    public function testRename()
    {
        $this->createFileWith(self::PATH, '');

        $newPath = self::PATH . '.moved';

        $coroutine = new Coroutine(File\rename(self::PATH, $newPath));

        try {
            $this->assertTrue($coroutine->wait());
            $this->assertTrue(is_file($newPath));
        } finally {
            @unlink($newPath);
        }
    }

    public function testLink()
    {
        $this->createFileWith(self::PATH, '');

        $link = self::PATH . '.link';

        $coroutine = new Coroutine(File\link(self::PATH, $link));

        try {
            $this->assertTrue($coroutine->wait());
            $this->assertTrue(is_file($link));
        } finally {
            @unlink($link);
        }
    }

    public function testSymlink()
    {
        $this->createFileWith(self::PATH, '');

        $link = self::PATH . '.symlink';

        $coroutine = new Coroutine(File\symlink(self::PATH, $link));

        try {
            $this->assertTrue($coroutine->wait());
            $this->assertTrue(is_file($link));
        } finally {
            @unlink($link);
        }
    }

    /**
     * @depends testSymlink
     */
    public function testReadlink()
    {
        $this->createFileWith(self::PATH, '');

        $link = self::PATH . '.symlink';

        $coroutine = new Coroutine(File\symlink(self::PATH, $link));

        try {
            $this->assertTrue($coroutine->wait());

            $coroutine = new Coroutine(File\readlink($link));

            $this->assertSame(self::PATH, $coroutine->wait());
        } finally {
            @unlink($link);
        }
    }

    public function testCopy()
    {
        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $newPath = self::PATH . '.copy';

        $coroutine = new Coroutine(File\copy(self::PATH, $newPath));

        try {
            $this->assertSame(strlen(self::WRITE_STRING), $coroutine->wait());
            $this->assertTrue(is_file($newPath));
        } finally {
            @unlink($newPath);
        }
    }

    public function testIsFile()
    {
        $this->createFileWith(self::PATH, '');

        $coroutine = new Coroutine(File\isFile(self::PATH));

        $this->assertTrue($coroutine->wait());
    }

    public function testIsDir()
    {
        $this->createDirectory(self::DIR);

        $coroutine = new Coroutine(File\isDir(self::DIR));

        $this->assertTrue($coroutine->wait());
    }

    public function testLsDir()
    {
        $path1 = self::DIR . '/test1.txt';
        $path2 = self::DIR . '/test2.txt';

        $this->createDirectory(self::DIR);
        $this->createFileWith($path1, 'testing1');
        $this->createFileWith($path2, 'testing2');

        try {
            $coroutine = new Coroutine(File\lsDir(self::DIR));
            $ls = $coroutine->wait();

            $this->assertSame($ls, ['test1.txt', 'test2.txt']);
        } finally {
            unlink($path1);
            unlink($path2);
            rmdir(self::DIR);
        }
    }

    public function testRmDir()
    {
        $this->createDirectory(self::DIR);
        $coroutine = new Coroutine(File\rmDir(self::DIR));
        $this->assertTrue($coroutine->wait());
        $this->assertFalse(is_dir(self::DIR));
    }

    public function testChmod()
    {
        $mode = 0777;

        $this->createFileWith(self::PATH, self::WRITE_STRING);

        $coroutine = new Coroutine(File\chmod(self::PATH, $mode));
        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($mode, $stat['mode'] & 0777);
    }

    public function testChown()
    {
        $uid = getmyuid();

        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine(File\chown(self::PATH, $uid));
        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($uid, $stat['uid']);
    }

    public function testChgrp()
    {
        $gid = getmygid();

        $this->createFileWith(self::PATH, 'testing');

        $coroutine = new Coroutine(File\chgrp(self::PATH, $gid));
        $this->assertTrue($coroutine->wait());

        $stat = stat(self::PATH);
        $this->assertSame($gid, $stat['gid']);
    }
}
