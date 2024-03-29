# TestFs

[![CI](https://github.com/christeredvartsen/testfs/actions/workflows/ci.yml/badge.svg)](https://github.com/christeredvartsen/testfs/actions/workflows/ci.yml)

Virtual filesystem for PHP for use with testing, implemented using a stream wrapper.

## Requirements

This library requires PHP >= 8.1.

## Installation

Install using [Composer](https://getcomposer.org):

```
composer require christeredvartsen/testfs
```

## Usage

To enable the stream wrapper you must first register it:

```php
TestFs\StreamWrapper::register();
```

When it is registered it will pick up usage of the `tfs://` protocol used with filesystem functions, for instance `fopen()`, `file_get_contents()`, `touch()` and so forth.

You can also fetch the "root" of the virtual filesystem after registering the wrapper to inspect the assets (files and/or directories within the virtual filesystem):

```php
$root = TestFs\StreamWrapper::getRoot();
```

## Development

```
git clone git@github.com:christeredvartsen/testfs.git
composer install
composer run ci
```

## License

Licensed under the MIT License.
