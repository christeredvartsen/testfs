<?php declare(strict_types=1);
namespace TestFs;

use ArrayIterator;
use TestFs\Exception\DuplicateGroupException;
use TestFs\Exception\DuplicateUserException;
use TestFs\Exception\InvalidFopenModeException;
use TestFs\Exception\InvalidUrlException;
use TestFs\Exception\ProtocolAlreadyRegisteredException;
use TestFs\Exception\UnknownGroupException;
use TestFs\Exception\UnknownUserException;

class StreamWrapper
{
    /**
     * Resource updated by PHP
     *
     * @var ?resource
     */
    public $context;

    /**
     * Device asset
     */
    private static ?Device $device = null;

    /**
     * Wrapper protocol name
     */
    private static string $protocol = 'tfs';

    /**
     * User ID of the user
     */
    private static int $uid = 0;

    /**
     * Default user database
     *
     * This database is set when registering the wrapper
     *
     * @var array<int,string>
     */
    private static array $defaultUsers = [
        0 => 'root',
    ];

    /**
     * User database
     *
     * @var array<int,string>
     */
    private static array $users = [];

    /**
     * Group ID of the user
     */
    private static int $gid = 0;

    /**
     * Default group database
     *
     * This database is set when registering the wrapper
     *
     * @var array<int,array{name:string,members:array<int>}>
     */
    private static array $defaultGroups = [
        0 => [
            'name'    => 'root',
            'members' => [0],
        ],
    ];

    /**
     * Group database
     *
     * @var array<int,array{name:string,members:array<int>}>
     */
    private static $groups = [];

    /**
     * Iterator used for the opendir/readdir/resetdir/closedir functions
     *
     * @var ?ArrayIterator<int,Asset>
     */
    private ?ArrayIterator $directoryIterator = null;

    /**
     * Handle used for fopen() and related functions
     */
    private ?File $fileHandle = null;

    /**
     * Add a user
     *
     * @throws DuplicateUserException
     */
    public static function addUser(int $uid, string $name): void
    {
        if (isset(self::$users[$uid])) {
            throw new DuplicateUserException($uid);
        }

        self::$users[$uid] = $name;
    }

    /**
     * Add a group
     *
     * @param list<int> $members
     * @throws DuplicateGroupException
     */
    public static function addGroup(int $gid, string $name, array $members = []): void
    {
        if (isset(self::$groups[$gid])) {
            throw new DuplicateGroupException($gid);
        }

        self::$groups[$gid] = [
            'name'    => $name,
            'members' => $members,
        ];
    }

    /**
     * Get the current user ID
     */
    public static function getUid(): int
    {
        return self::$uid;
    }

    /**
     * Set the current user ID
     *
     * @throws UnknownUserException
     */
    public static function setUid(int $uid): void
    {
        if (!isset(self::$users[$uid])) {
            throw new UnknownUserException($uid);
        }

        self::$uid = $uid;
    }

    /**
     * Get the current group ID
     */
    public static function getGid(): int
    {
        return self::$gid;
    }

    /**
     * Set the current group ID
     *
     * @throws UnknownGroupException
     */
    public static function setGid(int $gid): void
    {
        if (!isset(self::$groups[$gid])) {
            throw new UnknownGroupException($gid);
        }

        self::$gid = $gid;
    }

    /**
     * Register the stream wrapper and create the filesystem device
     *
     * @see https://www.php.net/manual/en/function.stream-wrapper-register.php
     *
     * @throws ProtocolAlreadyRegisteredException
     */
    public static function register(bool $force = false, int $uid = 0, int $gid = 0): bool
    {
        $exists = in_array(self::$protocol, stream_get_wrappers());

        if ($exists && !$force) {
            throw new ProtocolAlreadyRegisteredException(self::$protocol);
        } elseif ($exists) {
            self::unregister();
        }

        self::$users  = self::$defaultUsers;
        self::$groups = self::$defaultGroups;
        self::setUid($uid);
        self::setGid($gid);

        self::$device = new Device();

        return stream_wrapper_register(self::$protocol, self::class);
    }

    /**
     * Un-register the stream wrapper and destroy the filesystem device
     *
     * @see https://www.php.net/manual/en/function.stream-wrapper-unregister.php
     */
    public static function unregister(): bool
    {
        self::$device = null;
        self::$uid    = 0;
        self::$gid    = 0;
        self::$users  = [];
        self::$groups = [];

        return stream_wrapper_unregister(self::$protocol);
    }

    /**
     * Get the filesystem device
     */
    public static function getDevice(): ?Device
    {
        return self::$device;
    }

    /**
     * Get a TestFs URL given a path
     */
    public static function url(string $path): string
    {
        return sprintf('%s://%s', self::$protocol, ltrim($path, '/'));
    }

    /**
     * Get a path given a stream URL
     *
     * @throws InvalidUrlException
     */
    public static function urlToPath(string $url): string
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== self::$protocol) {
            throw new InvalidUrlException($url);
        }

        $path = sprintf(
            '%s/%s',
            $parts['host'] ?? '',
            $parts['path'] ?? '',
        );

        $path = preg_replace('|[/\\\\]+|', '/', $path);
        $path = trim((string) $path, '/');

        return rawurldecode($path);
    }

    /**
     * Close directory handle
     *
     * Rewind the directory, then unset the internal reference to the asset.
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-closedir.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function dir_closedir(): true
    {
        $this->directoryIterator = null;
        return true;
    }

    /**
     * Open directory
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-opendir.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function dir_opendir(string $path, int $options): bool
    {
        $asset = $this->getAssetFromUrl($path);

        if (null === $asset) {
            $this->warn(sprintf('opendir(%s): failed to open dir: No such file or directory', $path));
            return false;
        }

        if (!($asset instanceof Directory)) {
            $this->warn(sprintf('opendir(%s): failed to open dir: Not a directory', $path));
            return false;
        }

        if (!$asset->isReadable(self::$uid, self::$gid)) {
            $this->warn(sprintf('Warning: opendir(%s): failed to open dir: Permission denied', $path));
            return false;
        }

        $this->directoryIterator = new ArrayIterator($asset->getChildren());

        return true;
    }

    /**
     * Return the next filename
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-readdir.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function dir_readdir(): false|string
    {
        if (!$this->directoryIterator instanceof ArrayIterator) {
            return false;
        }

        if (!$this->directoryIterator->valid()) {
            return false;
        }

        $child = $this->directoryIterator->current();
        $this->directoryIterator->next();

        return $child->getName();
    }

    /**
     * Rewind directory handle
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-rewinddir.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function dir_rewinddir(): bool
    {
        if (null === $this->directoryIterator) {
            return false;
        }

        $this->directoryIterator->rewind();
        return true;
    }

    /**
     * Create a directory
     *
     * @see https://www.php.net/manual/en/streamwrapper.mkdir.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function mkdir(string $path, int $mode, int $options): bool
    {
        $path = $this->urlToPath($path);
        $current = self::$device?->getRoot();

        if (null === $current) {
            $this->warn('mkdir(): Stream wrapper has not been properly initialized');
            return false;
        }

        $dirs = array_filter(explode('/', $path));
        $numParts = count($dirs);
        $recursive = (bool) (STREAM_MKDIR_RECURSIVE & $options);

        foreach ($dirs as $i => $name) {
            $lastPart = $i + 1 === $numParts;
            $child = $current->getChild($name);

            if ($lastPart && null !== $child) {
                $this->warn('mkdir(): File exists');
                return false;
            }

            if (!$lastPart && !$recursive && null === $child) {
                $this->warn('mkdir(): No such file or directory');
                return false;
            }

            if (null === $child) {
                if (!$current->isWritable(self::$uid, self::$gid)) {
                    $this->warn('mkdir(): Permission denied');
                    return false;
                }

                $child = new Directory($name);
                $child->setMode($mode);
                $current->addChild($child);
            }

            $current = $child;
        }

        return true;
    }

    /**
     * Rename a file or directory
     *
     * Attempts to rename oldname to newname, moving it between directories if necessary. If
     * renaming a file and newname exists, it will be overwritten. If renaming a directory and
     * newname exists, this function will emit a warning.
     *
     * @see https://www.php.net/manual/en/streamwrapper.rename.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function rename(string $from, string $to): bool
    {
        $origin = $this->getAssetFromUrl($from);
        $target = $this->getAssetFromUrl($to);
        $targetParent = $this->getAssetParent($this->urlToPath($to));

        if (null === $origin || null === $targetParent) {
            $this->warn(sprintf('rename(%s,%s): No such file or directory', $from, $to));
            return false;
        }

        if ($target instanceof Directory) {
            $this->warn(sprintf('rename(%s,%s): Is a directory', $from, $to));
            return false;
        }

        if (($origin instanceof Directory) && ($target instanceof File)) {
            $this->warn(sprintf('rename(%s,%s): Not a directory', $from, $to));
            return false;
        }

        if (null !== $target) {
            $target->detach();
        }

        $origin->setName(basename($to));
        $targetParent->addChild($origin);

        return true;
    }

    /**
     * Remove a directory
     *
     * @see https://www.php.net/manual/en/streamwrapper.rmdir.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function rmdir(string $path, int $options): bool
    {
        $path = $this->urlToPath($path);
        $asset = $this->getAsset($path);

        if (null === $asset) {
            $this->warn(sprintf('rmdir(%s): No such file or directory', $path));
            return false;
        }

        if (!($asset instanceof Directory)) {
            $this->warn(sprintf('rmdir(%s): Not a directory', $path));
            return false;
        }

        if (!$asset->isEmpty()) {
            $this->warn(sprintf('rmdir(%s): Not empty', $path));
            return false;
        }

        if (!$asset->isWritable(self::$uid, self::$gid)) {
            $this->warn(sprintf('rmdir(%s): Permission denied', $path));
            return false;
        }

        $asset->getParent()?->removeChild($asset->getName());

        return true;
    }

    /**
     * Retrieve the underlaying resource
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-cast.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_cast(int $castAs): false
    {
        return false;
    }

    /**
     * Close the current file handle
     *
     * This method will also rewind the internal pointer in the file asset
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-close.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_close(): void
    {
        if (!$this->fileHandle instanceof File) {
            return;
        }

        $this->fileHandle->setAppendMode(false);
        $this->fileHandle->rewind();
        $this->fileHandle->unlock($this->getLockId());
        $this->fileHandle = null;
    }

    /**
     * Check if the stream is EOF
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-eof.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_eof(): bool
    {
        return $this->fileHandle?->eof() ?? true;
    }

    /**
     * Flush output
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-flush.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_flush(): false
    {
        return false;
    }

    /**
     * File locking
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-lock.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_lock(int $operation): bool
    {
        if (LOCK_NB === (LOCK_NB & $operation)) {
            $operation -= LOCK_NB;
        }

        return $this->fileHandle?->lock($this->getLockId(), $operation) ?? false;
    }

    /**
     * Get stream metadata
     *
     * @param mixed $value
     * @see https://www.php.net/manual/en/streamwrapper.stream-metadata.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_metadata(string $path, int $option, $value): bool
    {
        $path = $this->urlToPath($path);
        $asset = $this->getAsset($path);

        switch ($option) {
            case STREAM_META_TOUCH: // touch()
                if (null === $asset) {
                    $parent = $this->getAssetParent($path);

                    if ($parent === null) {
                        $this->warn(sprintf(
                            'touch(): Unable to create file %s because No such file or directory',
                            $path,
                        ));
                        return false;
                    }

                    $asset = new File(basename($path));
                    $parent->addChild($asset);
                }

                if (empty($value) || !is_array($value)) {
                    $mtime = $atime = time();
                } else {
                    [$mtime, $atime] = $value;
                }

                $asset->updateLastModified((int) $mtime);
                $asset->updateLastAccessed((int) $atime);
                break;

            case STREAM_META_OWNER_NAME: // chown() with user name
            case STREAM_META_OWNER:      // chown() with user number
                if (is_string($value)) {
                    $uid = array_flip(self::$users)[$value] ?? null;

                    if (null === $uid) {
                        $this->warn(sprintf('chown(): Unable to find uid for %s', $value));
                        return false;
                    }
                } else {
                    // chown() is not supposed to fail for non-existing UIDs at this point, so $uid
                    // might end up as null
                    $uid = isset(self::$users[(int) $value]) ? (int) $value : null;
                }

                if (null === $asset) {
                    $this->warn('chown(): No such file or directory');
                    return false;
                }

                if (null === $uid) {
                    $this->warn('chown(): Operation not permitted');
                    return false;
                }

                if (0 !== self::$uid && (!$asset->isOwnedByUser(self::$uid) || self::$uid !== $uid)) {
                    $this->warn('chown(): Operation not permitted');
                    return false;
                }

                $asset->setUid($uid);
                break;

            case STREAM_META_GROUP_NAME: // chgrp() with group name
            case STREAM_META_GROUP:      // chgrp() with group number
                if (is_string($value)) {
                    $gid = array_flip(array_column(self::$groups, 'name'))[$value] ?? null;

                    if (null === $gid) {
                        $this->warn(sprintf('chgrp(): Unable to find gid for %s', $value));
                        return false;
                    }
                } else {
                    // chgrp() is not supposed to fail for non-existing GIDs at this point, so $gid
                    // might end up as null
                    $gid = isset(self::$groups[(int) $value]) ? (int) $value : null;
                }

                if (null === $asset) {
                    $this->warn('chgrp(): No such file or directory');
                    return false;
                }

                if (null === $gid) {
                    $this->warn('chgrp(): Operation not permitted');
                    return false;
                }

                if (0 !== self::$uid && (!$asset->isOwnedByUser(self::$uid) || !$this->userIsInGroup(self::$uid, $gid))) {
                    $this->warn('chgrp(): Operation not permitted');
                    return false;
                }

                $asset->setGid($gid);
                break;

            case STREAM_META_ACCESS: // chmod
                if (null === $asset) {
                    $this->warn('chmod(): No such file or directory');

                    return false;
                }

                $asset->setMode((int) $value);
                break;
        }

        return true;
    }

    /**
     * Open file
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-open.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $path = $this->urlToPath($path);
        $asset = $this->getAsset($path);
        $parent = $this->getAssetParent($path);

        if ((bool) (STREAM_USE_PATH & $options)) {
            $this->warn('TestFs does not support "use_include_path"');
            return false;
        }

        if (null === $parent) {
            $this->warn(sprintf('fopen(%s): failed to open stream: No such file or directory', $path));
            return false;
        }

        try {
            $mode = $this->parseFopenMode($mode);
        } catch (InvalidFopenModeException $e) {
            $this->warn(sprintf('fopen(): %s', $e->getMessage()));
            return false;
        }

        if ($asset instanceof Directory) {
            $this->warn(sprintf('fopen(%s): failed to open stream. Is a directory', $path));
            return false;
        }

        if (null === $asset && !$mode->create()) {
            $this->warn(sprintf('fopen(%s): failed to open stream: No such file or directory', $path));
            return false;
        }

        if (null === $asset && !$parent->isWritable(self::$uid, self::$gid)) {
            $this->warn(sprintf('fopen(%s): failed to open stream: Permission denied', $path));
            return false;
        }

        if (null !== $asset && $mode->read() && !$asset->isReadable(self::$uid, self::$gid)) {
            $this->warn(sprintf('fopen(%s): failed to open stream: Permission denied', $path));
            return false;
        }

        if (null === $asset) {
            $asset = new File(basename($path));
            $parent->addChild($asset);
        }

        assert($asset instanceof File);

        $asset->setRead($mode->read());
        $asset->setWrite($mode->write());

        if ($mode->truncate()) {
            $asset->truncate();
        }

        if (SEEK_END === $mode->offset()) {
            $asset->setAppendMode(true);
        }

        $this->fileHandle = $asset;

        return true;
    }

    /**
     * Read from a stream
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-read.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_read(int $count): string|false
    {
        return $this->fileHandle?->read($count) ?? false;
    }

    /**
     * Move internal pointer in the file asset
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-seek.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return $this->fileHandle?->seek($offset, $whence) ?? false;
    }

    /**
     * Set stream options
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-set-option.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_set_option(int $option, int $arg1, ?int $arg2): false
    {
        return false;
    }

    /**
     * Stream stat
     *
     * @return array<mixed>|false
     * @see https://www.php.net/manual/en/streamwrapper.stream-stat.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_stat(): array|false
    {
        if (!$this->fileHandle instanceof File) {
            return false;
        }

        return $this->assetStat($this->fileHandle);
    }

    /**
     * Get the current offset in the file
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-tell.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_tell(): int
    {
        return $this->fileHandle?->getOffset() ?? 0;
    }

    /**
     * Truncate file
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-truncate.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_truncate(int $size): bool
    {
        return $this->fileHandle?->truncate($size) ?? false;
    }

    /**
     * Write to a stream
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-write.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function stream_write(string $data): int
    {
        return $this->fileHandle?->write($data) ?? 0;
    }

    /**
     * Remove a file
     *
     * @see https://www.php.net/manual/en/streamwrapper.unlink.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function unlink(string $path): bool
    {
        $path = $this->urlToPath($path);
        $asset = $this->getAsset($path);

        if (null === $asset) {
            $this->warn(sprintf('unlink(%s): No such file or directory', $path));
            return false;
        }

        if ($asset instanceof Directory) {
            $this->warn(sprintf('unlink(%s): Is a directory', $path));
            return false;
        }

        $parent = $asset->getParent();

        if (null === $parent) {
            $this->warn(sprintf('unlink(%s): No such file or directory', $path));
            return false;
        }

        if (!$parent->isWritable(self::$uid, self::$gid)) {
            $this->warn(sprintf('unlink(%s): Permission denied', $path));
            return false;
        }

        $asset->detach();

        return true;
    }

    /**
     * Retrieve information about a file
     *
     * @return array<mixed>|false
     * @see https://www.php.net/manual/en/streamwrapper.url-stat.php
     * @internal This method is not meant to be called directly from userland code
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $asset = $this->getAssetFromUrl($path);

        if (null === $asset) {
            if (!($flags & STREAM_URL_STAT_QUIET)) {
                $this->warn(sprintf('stat(): stat failed for %s', $path));
            }

            return false;
        }

        if (!$asset->isReadable(self::$uid, self::$gid)) {
            $this->warn(sprintf('stat(): stat failed for %s', $path));
            return false;
        }

        return $this->assetStat($asset);
    }

    /**
     * Get the asset at a specific path
     */
    private function getAsset(string $path): ?Asset
    {
        $parts = array_filter(explode('/', $path));
        $current = self::$device?->getRoot();

        foreach ($parts as $part) {
            $child = $current?->getChild($part);

            if (null === $child) {
                return null;
            }

            $current = $child;
        }

        return $current;
    }

    /**
     * Get the parent asset at a specific path
     *
     * Given "foo/bar/baz.txt" this method will return the "foo/bar" directory, if it exists.
     */
    private function getAssetParent(string $path): ?Directory
    {
        $parentPath = implode('/', array_slice(explode('/', $path), 0, -1));

        if ('' !== $parentPath) {
            $asset = $this->getAsset($parentPath);
            return $asset instanceof Directory ? $asset : null;
        }

        return self::$device?->getRoot();
    }

    /**
     * Trigger a warning
     */
    private function warn(string $message): void
    {
        trigger_error($message, E_USER_WARNING);
    }

    /**
     * Parse the mode used with fopen()
     *
     * @throws InvalidFopenModeException
     */
    private function parseFopenMode(string $mode): FopenMode
    {
        if (1 !== preg_match('/^(?P<mode>r|w|a|x|c)(?P<extra>b|t)?(?P<extended>\+)?$/', $mode, $match)) {
            throw new InvalidFopenModeException($mode);
        }

        return new FopenMode(
            $match['mode'],
            !empty($match['extended']),
            $match['extra'] ?? null,
        );
    }

    /**
     * Get an asset from a URL
     */
    private function getAssetFromUrl(string $url): ?Asset
    {
        return $this->getAsset($this->urlToPath($url));
    }

    /**
     * Stat an asset
     *
     * @return array<mixed>
     */
    private function assetStat(Asset $asset): array
    {
        $stat = [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => $asset->getType() | $asset->getMode(),
            'nlink'   => 0,
            'uid'     => $asset->getUid(),
            'gid'     => $asset->getGid(),
            'rdev'    => 0,
            'size'    => $asset->getSize(),
            'atime'   => $asset->getLastAccessed(),
            'mtime'   => $asset->getLastModified(),
            'ctime'   => $asset->getLastMetadataModified(),
            'blksize' => 0,
            'blocks'  => 0,
        ];

        return array_merge(array_values($stat), $stat);
    }

    /**
     * Get the ID of this instance used for locking
     */
    private function getLockId(): string
    {
        return spl_object_hash($this);
    }

    /**
     * Check if a user is a member of a group
     */
    private function userIsInGroup(int $uid, int $gid): bool
    {
        if (empty(self::$groups[$gid]['members'])) {
            return false;
        }

        $members = array_flip(self::$groups[$gid]['members']);

        return isset($members[$uid]);
    }
}
