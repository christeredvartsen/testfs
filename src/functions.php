<?php declare(strict_types=1);
/**
 * @codeCoverageIgnoreStart
 */
namespace TestFs;

/**
 * Convert a regular path to a TestFS URL
 *
 * @param string $path Regular file path
 * @return string
 */
function url(string $path) : string {
    return StreamWrapper::url($path);
}

/**
 * @see https://www.php.net/manual/en/function.opendir.php
 */
function opendir($path, $context = null) {
    return \opendir(url($path), $context);
}

/**
 * @see https://www.php.net/manual/en/streamwrapper.mkdir.php
 */
function mkdir($path, $mode = 0777, $recursive = false, $context = null) {
    return \mkdir(url($path), $mode, $recursive, $context);
}

/**
 * @see https://www.php.net/manual/en/function.rename.php
 */
function rename($oldname, $newname, $context = null) {
    return \rename(url($oldname), url($newname), $context);
}

/**
 * @see https://www.php.net/manual/en/function.rmdir.php
 */
function rmdir($dirname, $context = null) {
    return \rmdir(url($dirname), $context);
}

/**
 * @see https://www.php.net/manual/en/function.touch.php
 */
function touch($filename, $time = null, $atime = null) {
    return \touch(url($filename), $time, $atime);
}

/**
 * @see https://www.php.net/manual/en/function.chmod.php
 */
function chmod($filename, $mode) {
    return \chmod($filename, $mode);
}

/**
 * @see https://www.php.net/manual/en/function.chown.php
 */
function chown($filename, $user) {
    return \chown(url($filename), $user);
}

/**
 * @see https://www.php.net/manual/en/function.chgrp.php
 */
function chgrp($filename, $group) {
    return \chgrp(url($filename), $group);
}

/**
 * @see https://www.php.net/manual/en/function.fopen.php
 */
function fopen($filename, $mode, $use_include_path = false, $context = null) {
    return \fopen(url($filename), $mode, $use_include_path, $context);
}

/**
 * @see https://www.php.net/manual/en/function.file-get-contents.php
 */
function file_get_contents($filename, $use_include_path = false, $context = 0, $offset = 0, $maxlen = null) {
    return \file_get_contents(url($filename), $use_include_path, $context, $offset, $maxlen);
}

/**
 * @see https://www.php.net/manual/en/function.unlink.php
 */
function unlink($filename, $context = null) {
    return \unlink(url($filename), $context);
}