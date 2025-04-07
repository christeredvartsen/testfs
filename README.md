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

### Mirror a local directory into the virtual filesystem

If you want to mirror a local directory into the virtual filesystem you can do this:

```php
require 'vendor/autoload.php';

use TestFs\StreamWrapper;

StreamWrapper::register();

$device = StreamWrapper::getDevice();
$device->buildFromDirectory('./src');

echo $device->tree() . PHP_EOL;
```

If the above code would be executed from a PHP file in the root of this project you would get something like this:

```
/
├── Asset.php
├── Device.php
├── Directory.php
├── Exception
│   ├── DuplicateAssetException.php
│   ├── DuplicateGroupException.php
│   ├── DuplicateUserException.php
│   ├── InsufficientStorageException.php
│   ├── InvalidAssetNameException.php
│   ├── InvalidFopenModeException.php
│   ├── InvalidUrlException.php
│   ├── InvalidWhenceException.php
│   ├── ProtocolAlreadyRegisteredException.php
│   ├── RuntimeException.php
│   ├── UnknownAssetException.php
│   ├── UnknownGroupException.php
│   └── UnknownUserException.php
├── File.php
├── FopenMode.php
├── RootDirectory.php
├── StreamWrapper.php
└── functions.php

1 directory, 21 files
```

### Converting paths to URLs

To convert regular file paths to URLs that will be picked up by TestFs you can use the `TestFs::url(string $path)` function:

```php
<?php
require 'vendor/autoload.php';

use function TestFs\url;

echo url('path/to/file.php'); // tfs://path/to/file.php

```

### Wrappers for regular filesystem functions

The library contains simple wrappers around some of the filesystem functions in PHP that automatically prefixes paths with the correct protocol:

```php
<?php
require 'vendor/autoload.php';

use TestFs\StreamWrapper;

use function TestFs\file_get_contents;
use function TestFs\file_put_contents;
use function TestFs\mkdir;

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
