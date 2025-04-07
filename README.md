# TestFs

[![CI](https://github.com/christeredvartsen/testfs/actions/workflows/ci.yml/badge.svg)](https://github.com/christeredvartsen/testfs/actions/workflows/ci.yml)

Virtual filesystem for PHP for use with testing, implemented using a [stream wrapper](https://www.php.net/manual/en/class.streamwrapper.php).

## Requirements

This library requires PHP >= 8.3.

## Installation

Install using [Composer](https://getcomposer.org):

```
composer require --dev christeredvartsen/testfs
```

## Usage

### Enable the stream wrapper

The stream wrapper is enabled once you register it:

```php
require 'vendor/autoload.php';

use TestFs\StreamWrapper;

StreamWrapper::register();
```

When it is registered it will pick up usage of the `tfs://` protocol used with filesystem functions, for instance `fopen()`, `file_get_contents()` and `mkdir()`.

### Converting paths to URLs

To convert regular file paths to URLs that will be picked up by TestFs you can use the `TestFs::url(string $path)` function:

```php
<?php
require 'vendor/autoload.php';

use function TestFs\url;

echo url('path/to/file.php'); // tfs://path/to/file.php

```

### Wrappers for regular file system functions

The library contains simple wrappers around some of the filesystem functions in PHP that automatically prefixes paths with the correct protocol:

```php
<?php
require 'vendor/autoload.php';

use TestFs\StreamWrapper;

use function TestFs\{
    file_get_contents,
    file_put_contents,
    mkdir,
};

StreamWrapper::register();

mkdir('foo/bar', 0777, true);
file_put_contents('foo/bar/baz.txt', 'Hello World!');

echo file_get_contents('foo/bar/baz.txt'); // Hello World!
```

Refer to [src/functions.php](src/functions.php) for the complete list of wrapped functions.

## Development

```
git clone git@github.com:christeredvartsen/testfs.git
composer install
composer run ci
```

## License

MIT, see [LICENSE](LICENSE).
