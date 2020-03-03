<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;
use TestFs\Exception\RuntimeException;
use ArrayIterator;

class StreamWrapper {
    /**
     * Resource updated by PHP
     *
     * @var ?resource
     */
    public $context;

    /**
     * Root asset
     *
     * @var ?Directory
     */
    private static $root;

    /**
     * Wrapper protocol
     *
     * @var string
     */
    private static $protocol = 'tfs';

    /**
     * Name of the root Directory instance
     *
     * @var string
     */
    private static $rootDirectoryName = '<root>';

    /**
     * Asset factory
     *
     * @var ?AssetFactory
     */
    private static $assetFactory;

    /**
     * User ID of the user
     *
     * @var int
     */
    private static $uid = 0;

    /**
     * Default user "database"
     *
     * This database is set when registering the wrapper
     *
     * @var array
     */
    private static $defaultUsers = [
        0 => 'root',
    ];

    /**
     * User "database"
     *
     * @var array
     */
    private static $users = [];

    /**
     * Group ID of the user
     *
     * @var int
     */
    private static $gid = 0;

    /**
     * Default group "database"
     *
     * This database is set when registering the wrapper
     *
     * @var array<array-key, array{name: string, members: array}>
     */
    private static $defaultGroups = [
        0 => [
            'name' => 'root',
            'members' => [0],
        ],
    ];

    /**
     * Group "database"
     *
     * @var array<array-key, array{name: string, members: array}>
     */
    private static $groups = [];

    /**
     * Iterator used for the opendir/readdir/resetdir/closedir functions
     *
     * @var ?ArrayIterator<int,Directory|File>
     */
    private $directoryIterator;

    /**
     * Handle used for fopen() and related functions
     *
     * @var ?File
     */
    private $fileHandle;

    /**
     * Add a user
     *
     * @param int $uid The UID of the user
     * @param string $name The name of the user
     * @throws InvalidArgumentException
     * @return void
     */
    public static function addUser(int $uid, string $name) : void {
        if (isset(self::$users[$uid])) {
            throw new InvalidArgumentException(sprintf('User with uid %d already exists', $uid));
        }

        self::$users[$uid] = $name;
    }

    /**
     * Add a group
     *
     * @param int $gid The GID of the group
     * @param string $name The name of the group
     * @param int[] $members A list of UIDs to add as members to the group
     * @throws InvalidArgumentException
     * @return void
     */
    public static function addGroup(int $gid, string $name, array $members = []) : void {
        if (isset(self::$groups[$gid])) {
            throw new InvalidArgumentException(sprintf('Group with gid %d already exists', $gid));
        }

        self::$groups[$gid] = [
            'name'    => $name,
            'members' => $members,
        ];
    }

    /**
     * Get the current user ID
     *
     * @return int
     */
    public static function getUid() : int {
        return self::$uid;
    }

    /**
     * Set the current user ID
     *
     * @param int $uid
     * @throws InvalidArgumentException Throws an exception if $uid does not exist
     * @return void
     */
    public static function setUid(int $uid) : void {
        if (!isset(self::$users[$uid])) {
            throw new InvalidArgumentException(sprintf('UID %d does not exist', $uid));
        }

        self::$uid = $uid;
    }

    /**
     * Get the current group ID
     *
     * @return int
     */
    public static function getGid() : int {
        return self::$gid;
    }

    /**
     * Set the current group ID
     *
     * @param int $gid
     * @return void
     */
    public static function setGid(int $gid) : void {
        if (!isset(self::$groups[$gid])) {
            throw new InvalidArgumentException(sprintf('GID %d does not exist', $gid));
        }

        self::$gid = $gid;
    }

    /**
     * Get the root directory name
     *
     * @return string
     */
    public static function getRootDirectoryName() : string {
        return self::$rootDirectoryName;
    }

    /**
     * Register the stream wrapper and create the filesystem root
     *
     * @see https://www.php.net/manual/en/function.stream-wrapper-register.php
     *
     * @param bool $force Set to true to unregister a possible existing wrapper with the same protocol
     * @param int $uid The user ID to use
     * @param int $gid The group ID to use
     * @throws RuntimeException Throws an exception if an existing stream wrapper exists, and we are not forcing the addition
     * @return bool True on success, false otherwise
     */
    public static function register(bool $force = false, int $uid = 0, int $gid = 0) : bool {
        $exists = in_array(self::$protocol, stream_get_wrappers());

        if ($exists && !$force) {
            throw new RuntimeException(sprintf('Protocol "%s" is already registered', self::$protocol));
        } else if ($exists) {
            self::unregister();
        }

        self::$users  = self::$defaultUsers;
        self::$groups = self::$defaultGroups;
        self::setUid($uid);
        self::setGid($gid);

        self::$root = new Directory(self::$rootDirectoryName);

        return stream_wrapper_register(self::$protocol, self::class);
    }

    /**
     * Un-register the stream wrapper and destroy the filesystem root
     *
     * @see https://www.php.net/manual/en/function.stream-wrapper-unregister.php
     *
     * @return bool True on success, false otherwise
     */
    public static function unregister() : bool {
        self::$root   = null;
        self::$uid    = 0;
        self::$gid    = 0;
        self::$users  = [];
        self::$groups = [];

        return stream_wrapper_unregister(self::$protocol);
    }

    /**
     * Get the filesystem root
     *
     * @return Directory|null
     */
    public static function getRoot() : ?Directory {
        return self::$root;
    }

    /**
     * Get a TestFs URL given a path
     *
     * @param string $path The path to get the URL to
     * @return string Returns the path with the protocol prefixed
     */
    public static function url(string $path) : string {
        return sprintf('%s://%s', self::$protocol, ltrim($path, '/'));
    }

    /**
     * Get a path given a stream URL
     *
     * @param string $url The URL to get the path to
     * @throws InvalidArgumentException Throws an exception if the URL is not valid
     * @return string
     */
    public static function urlToPath(string $url) : string {
        /** @var string[] */
        $parts = parse_url($url);

        if (empty($parts['scheme']) || $parts['scheme'] !== self::$protocol) {
            throw new InvalidArgumentException(sprintf('Invalid URL: %s', $url));
        }

        $path = sprintf(
            '%s/%s',
            $parts['host'] ?? '',
            $parts['path'] ?? ''
        );

        /** @var string */
        $path = preg_replace('|[/\\\\]+|', '/', $path);
        $path = trim($path, '/');

        return rawurldecode($path);
    }

    /**
     * Close directory handle
     *
     * Rewind the directory, then unset the internal reference to the asset.
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-closedir.php
     *
     * @return bool True on success, false otherwise
     */
    public function dir_closedir() : bool {
        $this->directoryIterator = null;

        return true;
    }

    /**
     * Open directory
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-opendir.php
     *
     * @param string $path The path to the directory
     * @param int $options Options for opening the directory. This parameter is ignored.
     * @return bool True on success, false otherwise
     */
    public function dir_opendir(string $path , int $options) : bool {
        $asset = $this->getAssetFromUrl($path);

        if (null === $asset) {
            $this->warn(sprintf('opendir(%s): failed to open dir: No such file or directory', $path));
            return false;
        } else if (!($asset instanceof Directory)) {
            $this->warn(sprintf('opendir(%s): failed to open dir: Not a directory', $path));
            return false;
        } else if (!$asset->isReadable(self::$uid, self::$gid)) {
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
     *
     * @throws RuntimeException
     * @return bool|string The next filename or false if there is no more files
     */
    public function dir_readdir() {
        if (!$this->directoryIterator instanceof ArrayIterator) {
            throw new RuntimeException('Invalid directory iterator');
        }

        $child = $this->directoryIterator->current();

        if (!$child instanceof Asset) {
            return false;
        }

        $this->directoryIterator->next();

        return $child->getName();
    }

    /**
     * Rewind directory handle
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-rewinddir.php
     *
     * @throws RuntimeException
     * @return bool Returns true on success, false otherwise
     */
    public function dir_rewinddir() : bool {
        if (!$this->directoryIterator instanceof ArrayIterator) {
            throw new RuntimeException('Invalid directory iterator');
        }

        $this->directoryIterator->rewind();
        return true;
    }

    /**
     * Create a directory
     *
     * @see https://www.php.net/manual/en/streamwrapper.mkdir.php
     *
     * @param string $path The path to create
     * @param int $mode Directory mode
     * @param int $options Options for directory creation
     * @return bool Returns true on success or false on failure
     */
    public function mkdir(string $path, int $mode, int $options) : bool {
        $path = $this->urlToPath($path);

        /** @var Directory */
        $current = self::$root;

        $dirs = array_filter(explode('/', $path));
        $numParts = count($dirs);
        $recursive = (bool) (STREAM_MKDIR_RECURSIVE & $options);

        foreach ($dirs as $i => $name) {
            $lastPart = $i + 1 === $numParts;
            $child = $current->getChild($name);

            if ($lastPart && null !== $child) {
                $this->warn('mkdir(): File exists');
                return false;
            } else if (!$lastPart && !$recursive && null === $child) {
                $this->warn('mkdir(): No such file or directory');
                return false;
            } else if (null === $child) {
                if (!$current->isWritable(self::$uid, self::$gid)) {
                    $this->warn('mkdir(): Permission denied');
                    return false;
                }

                $child = $this->getAssetFactory()->directory($name);
                $child->setMode($mode);
                $current->addChild($child);
            }

            /** @var Directory */
            $current = $child;
        }

        return true;
    }

    /**
     * Rename a file or directory
     *
     * @see https://www.php.net/manual/en/streamwrapper.rename.php
     *
     * Attempts to rename oldname to newname, moving it between directories if necessary. If
     * renaming a file and newname exists, it will be overwritten. If renaming a directory and
     * newname exists, this function will emit a warning.
     *
     * @param string $from The old name
     * @param string $to The new name
     * @return bool True on failure, false otherwise
     */
    public function rename(string $from, string $to) : bool {
        $origin = $this->getAssetFromUrl($from);
        $target = $this->getAssetFromUrl($to);
        $targetParent = $this->getAssetParent($this->urlToPath($to));

        if (null === $origin || null === $targetParent) {
            $this->warn(sprintf('rename(%s,%s): No such file or directory', $from, $to));
            return false;
        } else if ($target instanceof Directory) {
            $this->warn(sprintf('rename(%s,%s): Is a directory', $from, $to));
            return false;
        } else if (($origin instanceof Directory) && ($target instanceof File)) {
            $this->warn(sprintf('rename(%s,%s): Not a directory', $from, $to));
            return false;
        }

        if (null !== $target) {
            $target->detach();
        }

        $origin->detach();
        $origin->setName(basename($to));
        $targetParent->addChild($origin);

        return true;
    }

    /**
     * Remove a directory
     *
     * @see https://www.php.net/manual/en/streamwrapper.rmdir.php
     *
     * @param string $path The path to remove
     * @param int $options Options for removing
     * @return bool
     */
    public function rmdir(string $path, int $options) : bool {
        $path = $this->urlToPath($path);
        $asset = $this->getAsset($path);

        if (null === $asset) {
            $this->warn(sprintf('rmdir(%s): No such file or directory', $path));
            return false;
        } else if (!($asset instanceof Directory)) {
            $this->warn(sprintf('rmdir(%s): Not a directory', $path));
            return false;
        } else if (!$asset->isEmpty()) {
            $this->warn(sprintf('rmdir(%s): Not empty', $path));
            return false;
        } else if (!$asset->isWritable(self::$uid, self::$gid)) {
            $this->warn(sprintf('rmdir(%s): Permission denied', $path));
            return false;
        }

        /** @var Directory */
        $parent = $asset->getParent();
        $parent->removeChild($asset->getName());

        return true;
    }

    /**
     * Retrieve the underlaying resource
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-cast.php
     * @codeCoverageIgnore
     *
     * @param int $castAs
     * @return false
     */
    public function stream_cast(int $castAs) : bool {
        return false;
    }

    /**
     * Close the current file handle
     *
     * This method will also rewind the internal pointer in the file asset
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-close.php
     *
     * @throws RuntimeException
     * @return void
     */
    public function stream_close() : void {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
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
     *
     * @throws RuntimeException
     * @return bool
     */
    public function stream_eof() : bool {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
        }

        return $this->fileHandle->eof();
    }

    /**
     * Flush output
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-flush.php
     * @codeCoverageIgnore
     *
     * @return false
     */
    public function stream_flush() : bool {
        return false;
    }

    /**
     * File locking
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-lock.php
     *
     * @param int $operation
     * @throws RuntimeException
     * @return bool
     */
    public function stream_lock(int $operation) : bool {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
        }

        if (LOCK_NB === (LOCK_NB & $operation)) {
            $operation -= LOCK_NB;
        }

        return $this->fileHandle->lock($this->getLockId(), $operation);
    }

    /**
     * Get stream metadata
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-metadata.php
     * @todo Check permissions
     *
     * @param string $path The path to operate on
     * @param int $option The option to use
     * @param mixed $value The value to use
     * @return bool Returns false on failure, true otherwise
     */
    public function stream_metadata(string $path, int $option, $value) : bool {
        $path = $this->urlToPath($path);
        $asset = $this->getAsset($path);

        switch ($option) {
            case STREAM_META_TOUCH: // touch()
                if (null === $asset) {
                    $parent = $this->getAssetParent($path);

                    if ($parent === null) {
                        $this->warn(sprintf(
                            'touch(): Unable to create file %s because No such file or directory',
                            $path
                        ));
                        return false;
                    }

                    $asset = $this->getAssetFactory()->file(basename($path));
                    $parent->addChild($asset);
                }

                if (empty($value)) {
                    $mtime = $atime = time();
                } else {
                    [$mtime, $atime] = $value;
                }

                $asset->updateLastModified($mtime);
                $asset->updateLastAccessed($atime);

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
                    $uid = isset(self::$users[$value]) ? $value : null;
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
                    $gid = array_flip(array_map(function(array $group) : string {
                        return $group['name'];
                    }, self::$groups))[$value] ?? null;

                    if (null === $gid) {
                        $this->warn(sprintf('chgrp(): Unable to find gid for %s', $value));

                        return false;
                    }
                } else {
                    // chgrp() is not supposed to fail for non-existing GIDs at this point, so $gid
                    // might end up as null
                    $gid = isset(self::$groups[$value]) ? $value : null;
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

                $asset->setGid((int) $gid);

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
     *
     * @param string $path The path to open
     * @param string $mode The mode to open the file in
     * @param int $options Streams API options
     * @param string $opened_path Path that was actually opened. Updated if $options include STREAM_USE_PATH
     * @return bool True on success, false otherwise
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path) : bool {
        $path = $this->urlToPath($path);
        $asset = $this->getAsset($path);
        $parent = $this->getAssetParent($path);

        if ((bool) (STREAM_USE_PATH & $options)) {
            $this->warn('TestFs does not support "use_include_path"');

            return false;
        }

        if (null === $parent || !($parent instanceof Directory)) {
            $this->warn(sprintf('fopen(%s): failed to open stream: No such file or directory', $path));

            return false;
        }

        try {
            $mode = $this->parseFopenMode($mode);
        } catch (InvalidArgumentException $e) {
            $this->warn(sprintf('fopen(): %s', $e->getMessage()));

            return false;
        }

        if ($asset instanceof Directory && $mode->write()) {
            $this->warn(sprintf('fopen(%s): failed to open stream. Is a directory', $path));

            return false;
        } else if ($asset instanceof Directory) {
            return false;
        }

        if (null === $asset && !$mode->create()) {
            $this->warn(sprintf('fopen(%s): failed to open stream: No such file or directory', $path));

            return false;
        }

        if (null === $asset && !$parent->isWritable(self::$uid, self::$gid)) {
            $this->warn(sprintf('fopen(%s): failed to open stream: Permission denied', $path));

            return false;
        } else if (null === $asset) {
            $asset = $this->getAssetFactory()->file(basename($path));
            $parent->addChild($asset);
        } else if ($mode->read() && !$asset->isReadable(self::$uid, self::$gid)) {
            $this->warn(sprintf('fopen(%s): failed to open stream: Permission denied', $path));

            return false;
        }

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
     *
     * @param int $count The number of bytes to read
     * @throws RuntimeException
     * @return string
     */
    public function stream_read(int $count) : string {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
        }

        return $this->fileHandle->read($count);
    }

    /**
     * Move internal pointer in the file asset
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-seek.php
     *
     * @param int $offset The offset to use
     * @param int $whence From where to set the offset
     * @throws RuntimeException
     * @return bool Returns true on success, false on failure
     */
    public function stream_seek(int $offset, int $whence = SEEK_SET) : bool {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
        }

        return $this->fileHandle->seek($offset, $whence);
    }

    /**
     * Set stream options
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-set-option.php
     * @codeCoverageIgnore
     *
     * @param int $option The affected option
     * @param int $arg1 Argument for the option
     * @param int $arg2 Optional second argument for the option
     * @return bool True on an implemented option, false otherwise
     */
    public function stream_set_option(int $option, int $arg1, ?int $arg2) : bool {
        return false;
    }

    /**
     * Stream stat
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-stat.php
     *
     * @throws RuntimeException
     * @return array
     */
    public function stream_stat() : array {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
        }

        return $this->assetStat($this->fileHandle);
    }

    /**
     * Get the current offset in the file
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-tell.php
     *
     * @return int
     */
    public function stream_tell() : int {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
        }

        return $this->fileHandle->getOffset();
    }

    /**
     * Truncate file
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-truncate.php
     *
     * @param int $size The new size for the file
     * @return bool
     */
    public function stream_truncate(int $size) : bool {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
        }

        return $this->fileHandle->truncate($size);
    }

    /**
     * Write to a stream
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-write.php
     *
     * @param string $data The data to write
     * @return int Returns the number of bytes written
     */
    public function stream_write(string $data) : int {
        if (!$this->fileHandle instanceof File) {
            throw new RuntimeException('Invalid file handle');
        }

        return $this->fileHandle->write($data);
    }

    /**
     * Remove a file
     *
     * @see https://www.php.net/manual/en/streamwrapper.unlink.php
     *
     * @param string $path The path to remove
     * @return bool Returns true on success, false otherwise
     */
    public function unlink(string $path) : bool {
        $path = $this->urlToPath($path);
        $asset = $this->getAsset($path);

        /** @var Directory */
        $parent = null !== $asset ? $asset->getParent() : self::$root;

        if (null === $asset) {
            $this->warn(sprintf('unlink(%s): No such file or directory', $path));

            return false;
        } else if ($asset instanceof Directory) {
            $this->warn(sprintf('unlink(%s): Is a directory', $path));

            return false;
        } else if (!$parent->isWritable(self::$uid, self::$gid)) {
            $this->warn(sprintf('unlink(%s): Permission denied', $path));

            return false;
        }

        $asset->detach();

        return true;
    }

    /**
     * Retrieve information about a file
     *
     * @see https://www.php.net/manual/en/streamwrapper.url-stat.php
     *
     * @param string $path The path to check
     * @param int $flags Options
     * @return bool|array Returns false on failure, or an array with stat data otherwise
     */
    public function url_stat(string $path, int $flags) {
        $asset = $this->getAssetFromUrl($path);

        if (null === $asset) {
            if (!($flags & STREAM_URL_STAT_QUIET)) {
                $this->warn(sprintf('stat(): stat failed for %s', $path));
            }

            return false;
        } else if (!$asset->isReadable(self::$uid, self::$gid)) {
            $this->warn(sprintf('stat(): stat failed for %s', $path));

            return false;
        }

        return $this->assetStat($asset);
    }

    /**
     * Get an asset factory
     *
     * @codeCoverageIgnore
     * @return AssetFactory
     */
    private function getAssetFactory() : AssetFactory {
        if (null === self::$assetFactory) {
            self::$assetFactory = new AssetFactory();
        }

        return self::$assetFactory;
    }

    /**
     * Get the asset at a specific path
     *
     * @param string $path The path to get
     * @return File|Directory|null Returns null if the asset does not exist
     */
    private function getAsset(string $path) {
        $parts = array_filter(explode('/', $path));
        $current = self::$root;

        foreach ($parts as $part) {
            if (!$current instanceof Directory) {
                return null;
            }

            $child = $current->getChild($part);

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
     *
     * @param string $path The path to get the parent of
     * @return Directory|null Returns null if the asset does not exist
     */
    private function getAssetParent(string $path) : ?Directory {
        $parentPath = implode('/', array_slice(explode('/', $path), 0, -1));

        if ($parentPath) {
            /** @var Directory */
            $parent = $this->getAsset($parentPath);
        } else {
            $parent = self::$root;
        }

        return $parent;
    }

    /**
     * Trigger a warning
     *
     * @param string $message The warning message
     * @return void
     */
    private function warn(string $message) : void {
        trigger_error($message, E_USER_WARNING);
    }

    /**
     * Parse the mode used with fopen()
     *
     * @param string $mode The mode given to fopen()
     * @throws InvalidArgumentException Throws an exception if the mode is invalid
     * @return FopenMode
     */
    private function parseFopenMode(string $mode) : FopenMode {
        if (0 === preg_match('/^(?P<mode>r|w|a|x|c)(?P<extra>b|t)?(?P<extended>\+)?$/', $mode, $match)) {
            throw new InvalidArgumentException(sprintf('Unsupported mode: "%s"', $mode));
        }

        return new FopenMode(
            $match['mode'],
            !empty($match['extended']),
            $match['extra'] ?? null
        );
    }

    /**
     * Get an asset from a URL
     *
     * @param string $url TestFs URL
     * @return ?Asset Returns the asset if it exists, or null otherwise
     */
    private function getAssetFromUrl(string $url) : ?Asset {
        return $this->getAsset($this->urlToPath($url));
    }

    /**
     * Stat an asset
     *
     * @param Asset $asset The asset to stat
     * @return array
     */
    private function assetStat(Asset $asset) : array {
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
     *
     * @return string Returns a unique ID per instance
     */
    private function getLockId() : string {
        return spl_object_hash($this);
    }

    /**
     * Check if a user is a member of a group
     *
     * @param int $uid The UID to check
     * @param int $gid The GID to check
     * @return bool
     */
    private function userIsInGroup(int $uid, int $gid) : bool {
        if (empty(self::$groups[$gid]['members'])) {
            return false;
        }

        $members = array_flip(self::$groups[$gid]['members']);

        return isset($members[$uid]);
    }
}
