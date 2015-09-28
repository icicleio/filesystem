<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\File;
use Icicle\Loop;

Coroutine\create(function () {
    $path = __DIR__ . '/test.txt';
    $dir = dirname(__DIR__);

    /** @var \Icicle\File\FileInterface $file */
    $file = (yield File\open($path, 'w+'));

    try {
        var_dump(yield $file->write('testing'));

        var_dump(yield $file->seek(0));

        var_dump(yield $file->read());

        //var_dump(yield $file->chown($path, 500));
    } finally {
        $file->close();
    }

    var_dump(yield File\isFile($path));

    echo "chmod: ";
    var_dump(yield File\chmod($path, 0777));

    echo "chown: ";
    var_dump(yield File\chown($path, 500));

    var_dump(yield File\isDir($path));

    var_dump(yield File\unlink($path));

    var_dump(yield File\isFile($path));

    var_dump(yield File\mkDir($dir . '/new'));

    var_dump(yield File\readDir($dir));

    var_dump(yield File\rmDir($dir . '/new'));

})->done();

Loop\run();
