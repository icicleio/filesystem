# Asynchronous File Access for Icicle

Asynchronous filesystem access that is *always* non-blocking, no extensions required.

This library is a component for [Icicle](https://github.com/icicleio/icicle), providing asynchronous filesystem functions and abstracting files as asynchronous [streams](https://github.com/icicleio/stream). Like other Icicle components, this library uses [Coroutines](//github.com/icicleio/icicle/wiki/Coroutines) built from [Awaitables](https://github.com/icicleio/icicle/wiki/Awaitables) and [Generators](http://www.php.net/manual/en/language.generators.overview.php) to make writing asynchronous code more like writing synchronous code.

[![Build Status](https://img.shields.io/travis/icicleio/filesystem/v1.x.svg?style=flat-square)](https://travis-ci.org/icicleio/filesystem)
[![Coverage Status](https://img.shields.io/coveralls/icicleio/filesystem/v1.x.svg?style=flat-square)](https://coveralls.io/r/icicleio/filesystem)
[![Semantic Version](https://img.shields.io/github/release/icicleio/filesystem.svg?style=flat-square)](http://semver.org)
[![MIT License](https://img.shields.io/packagist/l/icicleio/filesystem.svg?style=flat-square)](LICENSE)
[![@icicleio on Twitter](https://img.shields.io/badge/twitter-%40icicleio-5189c7.svg?style=flat-square)](https://twitter.com/icicleio)

#### Documentation and Support

- [Full API Documentation](https://icicle.io/docs)
- [Official Twitter](https://twitter.com/icicleio)
- [Gitter Chat](https://gitter.im/icicleio/icicle)

##### Requirements

- PHP 5.5+ for v0.1.x branch (current stable) and v1.x branch (mirrors current stable)
- PHP 7 for v2.0 branch (under development) supporting generator delegation and return expressions

##### Installation

The recommended way to install is with the [Composer](http://getcomposer.org/) package manager. (See the [Composer installation guide](https://getcomposer.org/doc/00-intro.md) for information on installing and using Composer.)

Run the following command to use this library in your project: 

```bash
composer require icicleio/filesystem
```

You can also manually edit `composer.json` to add this library as a project requirement.

```js
// composer.json
{
    "require": {
        "icicleio/filesystem": "^0.1"
    }
}
```

##### Suggested

- [eio extension](http://php.net/manual/en/book.eio.php): Uses libeio to provide asynchronous file access (v1.2.6+ required).

#### Example

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\File;
use Icicle\Loop;

Coroutine\create(function () {
    $path = __DIR__ . '/test.txt';

    // Create and open the file for reading and writing.
    $file = (yield File\open($path, 'w+'));

    try {
        // Write data to file.
        $written = (yield $file->write('testing'));
        
        printf("Wrote %d bytes to file.\n", $written);
        
        // Seek to beginning of file.
        yield $file->seek(0);
        
        // Read data from file.
        $data = (yield $file->read());
    } finally {
        $file->close();
    }
    
    printf("Read data from file: %s\n", $data);
    
    // Remove file.
    yield File\unlink($path);
})->done();

Loop\run();
```