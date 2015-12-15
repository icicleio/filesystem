<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\File;
use Icicle\Loop;

Coroutine\create(function () {
    $path = __DIR__ . '/test.txt';
    $dir = __DIR__;

    /** @var \Icicle\File\File $file */
    $file = (yield File\open($path, 'w+'));

    try {
        echo 'write: ';
        var_dump(yield $file->write('testing'));

        echo 'seek: ';
        var_dump(yield $file->seek(0));

        echo 'read: ';
        var_dump(yield $file->read());
    } finally {
        $file->close();
    }

    echo 'isFile: ';
    var_dump(yield File\isFile($path));

    echo 'isDir: ';
    var_dump(yield File\isDir($path));

    echo 'unlink: ';
    var_dump(yield File\unlink($path));

    echo 'isFile (after unlink): ';
    var_dump(yield File\isFile($path));

    echo 'mkDir: ';
    var_dump(yield File\mkDir($dir . '/new'));

    echo 'lsDir: ';
    var_dump(yield File\lsDir($dir));

    echo 'rmDir: ';
    var_dump(yield File\rmDir($dir . '/new'));

})->done();

Loop\run();
