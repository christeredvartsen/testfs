<?php declare(strict_types=1);
namespace TestFs;

/**
 * Convert a regular path to a TestFs URL
 */
function url(string $path): string
{
    return StreamWrapper::url($path);
}

/**
 * @see https://www.php.net/manual/en/function.opendir.php
 * @param ?resource $context
 * @return resource|false
 */
function opendir(string $directory, $context = null)
{
    return \opendir(url($directory), $context);
}

/**
 * @see https://www.php.net/manual/en/function.mkdir.php
 * @param ?resource $context
 */
function mkdir(string $directory, int $permissions = 0777, bool $recursive = false, $context = null): bool
{
    return \mkdir(url($directory), $permissions, $recursive, $context);
}

/**
 * @see https://www.php.net/manual/en/function.rename.php
 * @param ?resource $context
 */
function rename(string $from, string $to, $context = null): bool
{
    return \rename(url($from), url($to), $context);
}

/**
 * @see https://www.php.net/manual/en/function.rmdir.php
 * @param ?resource $context
 */
function rmdir($directory, $context = null): bool
{
    return \rmdir(url($directory), $context);
}

/**
 * @see https://www.php.net/manual/en/function.touch.php
 */
function touch(string $filename, ?int $mtime = null, ?int $atime = null): bool
{
    return \touch(url($filename), $mtime, $atime);
}

/**
 * @see https://www.php.net/manual/en/function.chmod.php
 */
function chmod(string $filename, int $permissions): bool
{
    return \chmod(url($filename), $permissions);
}

/**
 * @see https://www.php.net/manual/en/function.chown.php
 */
function chown(string $filename, string|int $user): bool
{
    return \chown(url($filename), $user);
}

/**
 * @see https://www.php.net/manual/en/function.chgrp.php
 */
function chgrp(string $filename, string|int $group): bool
{
    return \chgrp(url($filename), $group);
}

/**
 * @see https://www.php.net/manual/en/function.fopen.php
 * @param ?resource $context
 * @return resource|false
 */
function fopen(string $filename, string $mode, bool $use_include_path = false, $context = null)
{
    return \fopen(url($filename), $mode, $use_include_path, $context);
}

/**
 * @see https://www.php.net/manual/en/function.file-get-contents.php
 * @param ?resource $context
 */
function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string|false
{
    return \file_get_contents(url($filename), $use_include_path, $context, $offset, $length);
}

/**
 * @see https://www.php.net/manual/en/function.unlink.php
 * @param ?resource $context
 */
function unlink(string $filename, $context = null): bool
{
    return \unlink(url($filename), $context);
}
