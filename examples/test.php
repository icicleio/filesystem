<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\File;
use Icicle\Loop;

Coroutine\create(function () {
    /** @var \Icicle\File\FileInterface $file */
    $file = (yield File\open(__DIR__ . '/test.txt', 'w+'));

    try {
        var_dump(yield $file->write('testing'));

        var_dump(yield $file->seek(0));

        var_dump(yield $file->read());
    } finally {
        $file->close();
    }
})->done();

Loop\run();
