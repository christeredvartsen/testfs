<?php declare(strict_types=1);

namespace TestFs;

use function chgrp as php_chgrp;
use function chmod as php_chmod;
use function chown as php_chown;
use function file_get_contents as php_file_get_contents;
use function file_put_contents as php_file_put_contents;
use function filetype as php_filetype;
use function fopen as php_fopen;
use function is_dir as php_is_dir;
use function mkdir as php_mkdir;
use function opendir as php_opendir;
use function rename as php_rename;
use function rmdir as php_rmdir;
use function stat as php_stat;
use function touch as php_touch;
use function unlink as php_unlink;

/**
 * @see https://www.php.net/manual/en/function.chgrp.php
 */
function chgrp(string $filename, string|int $group): bool
{
    return php_chgrp(url($filename), $group);
}

/**
 * @see https://www.php.net/manual/en/function.chmod.php
 */
function chmod(string $filename, int $permissions): bool
{
    return php_chmod(url($filename), $permissions);
}

/**
 * @see https://www.php.net/manual/en/function.chown.php
 */
function chown(string $filename, string|int $user): bool
{
    return php_chown(url($filename), $user);
}

/**
 * @see https://www.php.net/manual/en/function.file-get-contents.php
 *
 * @param ?positive-int $length
 * @param ?resource     $context
 */
function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string|false
{
    return php_file_get_contents(url($filename), $use_include_path, $context, $offset, $length);
}

/**
 * @see https://www.php.net/manual/en/function.file-put-contents.php
 *
 * @param mixed     $data    the data to write
 * @param ?resource $context
 */
function file_put_contents(string $filename, $data, int $flags = 0, $context = null): int|false
{
    return php_file_put_contents(url($filename), $data, $flags, $context);
}

/**
 * @see https://www.php.net/manual/en/function.filetype.php
 */
function filetype(string $filename): string|false
{
    return php_filetype(url($filename));
}

/**
 * @see https://www.php.net/manual/en/function.fopen.php
 *
 * @param ?resource $context
 *
 * @return resource|false
 */
function fopen(string $filename, string $mode, bool $use_include_path = false, $context = null)
{
    return php_fopen(url($filename), $mode, $use_include_path, $context);
}

/**
 * @see https://www.php.net/manual/en/function.is_dir.php
 */
function is_dir(string $filename): bool
{
    return php_is_dir(url($filename));
}

/**
 * @see https://www.php.net/manual/en/function.mkdir.php
 *
 * @param ?resource $context
 */
function mkdir(string $directory, int $permissions = 0777, bool $recursive = false, $context = null): bool
{
    return php_mkdir(url($directory), $permissions, $recursive, $context);
}

/**
 * @see https://www.php.net/manual/en/function.opendir.php
 *
 * @param ?resource $context
 *
 * @return resource|false
 */
function opendir(string $directory, $context = null)
{
    return php_opendir(url($directory), $context);
}

/**
 * @see https://www.php.net/manual/en/function.rename.php
 *
 * @param ?resource $context
 */
function rename(string $from, string $to, $context = null): bool
{
    return php_rename(url($from), url($to), $context);
}

/**
 * @see https://www.php.net/manual/en/function.rmdir.php
 *
 * @param ?resource $context
 */
function rmdir(string $directory, $context = null): bool
{
    return php_rmdir(url($directory), $context);
}

/**
 * @see https://www.php.net/manual/en/function.stat.php
 *
 * @return array<mixed>|false
 */
function stat(string $filename): array|false
{
    return php_stat(url($filename));
}

/**
 * @see https://www.php.net/manual/en/function.touch.php
 */
function touch(string $filename, ?int $mtime = null, ?int $atime = null): bool
{
    return php_touch(url($filename), $mtime, $atime);
}

/**
 * @see https://www.php.net/manual/en/function.unlink.php
 *
 * @param ?resource $context
 */
function unlink(string $filename, $context = null): bool
{
    return php_unlink(url($filename), $context);
}

/**
 * Convert a regular path to a TestFs URL.
 */
function url(string $path): string
{
    return StreamWrapper::url($path);
}
