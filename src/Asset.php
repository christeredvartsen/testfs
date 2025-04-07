<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\DuplicateAssetException;
use TestFs\Exception\InvalidAssetNameException;

abstract class Asset
{
    public const TYPE_FILE      = 0100000;
    public const TYPE_DIRECTORY = 0040000;

    /**
     * Name of the asset
     */
    private string $name;

    /**
     * Parent directory of the asset
     */
    private ?Directory $parent = null;

    /**
     * Last accessed time
     */
    protected int $atime;

    /**
     * Last modified time
     */
    protected int $mtime;

    /**
     * Last metadata changed time
     */
    protected int $ctime;

    /**
     * User ID of the asset
     */
    private int $uid;

    /**
     * Group ID of the asset
     */
    private int $gid;

    /**
     * Mode of the asset
     */
    protected int $mode;

    /**
     * Create a new asset
     */
    public function __construct(string $name)
    {
        $this->setName($name);

        $time        = time();
        $this->atime = $time;
        $this->mtime = $time;
        $this->ctime = $time;
        $this->uid   = StreamWrapper::getUid();
        $this->gid   = StreamWrapper::getGid();
        $this->mode  = $this->getDefaultMode();
    }

    /**
     * Get the asset type
     *
     * Should be one of the TYPE_ constants defined in this class.
     */
    abstract public function getType(): int;

    /**
     * Get the size of the asset in bytes, including child assets
     */
    abstract public function getSize(): int;

    /**
     * Get the default mode of the asset
     */
    abstract protected function getDefaultMode(): int;

    /**
     * Get last accessed timestamp
     */
    public function getLastAccessed(): int
    {
        return $this->atime;
    }

    /**
     * Set the last accessed timestamp
     *
     * If a value of 0 or below is specified, the current time will be used.
     */
    public function updateLastAccessed(int $atime = 0): void
    {
        $this->atime = 0 < $atime ? $atime : time();
    }

    /**
     * Get last modified timestamp
     */
    public function getLastModified(): int
    {
        return $this->mtime;
    }

    /**
     * Set the last modification timestamp
     *
     * If a value of 0 or below is specified, the current time will be used.
     */
    public function updateLastModified(int $mtime = 0): void
    {
        $this->mtime = 0 < $mtime ? $mtime : time();
    }

    /**
     * Get last inode change timestamp
     */
    public function getLastMetadataModified(): int
    {
        return $this->ctime;
    }

    /**
     * Set the last inode change timestamp
     *
     * If a value of 0 or below is specified, the current time will be used.
     */
    public function updateLastMetadataModified(int $ctime = 0): void
    {
        $this->ctime = 0 < $ctime ? $ctime : time();
    }

    /**
     * Set the UID of the asset
     */
    public function setUid(int $uid): void
    {
        $this->uid = $uid;
        $this->updateLastMetadataModified();
    }

    /**
     * Set the GID of the asset
     */
    public function setGid(int $gid): void
    {
        $this->gid = $gid;
        $this->updateLastMetadataModified();
    }

    /**
     * Get the UID
     */
    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * Get the GID
     */
    public function getGid(): int
    {
        return $this->gid;
    }

    /**
     * Set the name of the asset
     *
     * @throws InvalidAssetNameException
     * @throws DuplicateAssetException
     */
    public function setName(string $name): void
    {
        $name = trim($name);

        if (empty($name)) {
            throw new InvalidAssetNameException('Name can not be empty');
        }

        if (false !== strpos($name, DIRECTORY_SEPARATOR)) {
            throw new InvalidAssetNameException('Name can not contain a directory separator');
        }

        if (true === $this->parent?->hasChild($name)) {
            throw new DuplicateAssetException($this->parent, $name);
        }

        $this->name = $name;
    }

    /**
     * Get the asset name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Detach from parent
     *
     * Detaching a child without a parent is a no-op.
     */
    public function detach(): void
    {
        if (null === $this->parent) {
            return;
        }

        $this->parent->removeChild($this->getName());
        $this->parent = null;
    }

    /**
     * Set the parent directory
     *
     * Setting a new parent will detach the asset from the old parent.
     *
     * Setting the existing parent is a no-op.
     *
     * @throws DuplicateAssetException
     */
    protected function setParent(Directory $parent): void
    {
        if ($parent === $this->getParent()) {
            return;
        }

        if ($parent->hasChild($this->getName())) {
            throw new DuplicateAssetException($parent, $this);
        }

        if ($this->parent) {
            $this->detach();
        }

        $this->parent = $parent;
    }

    /**
     * Get the parent directory
     */
    public function getParent(): ?Directory
    {
        return $this->parent;
    }

    /**
     * Get the device that the asset is attached to
     */
    public function getDevice(): ?Device
    {
        return $this->parent?->getDevice();
    }

    /**
     * Set the mode
     */
    public function setMode(int $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Get the mode
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * Check if the asset is readable
     */
    public function isReadable(int $uid, int $gid): bool
    {
        if (0 === $uid || 0 === $gid) {
            return true;
        }

        if ($this->uid === $uid) {
            $check = 0400;
        } elseif ($this->gid === $gid) {
            $check = 0040;
        } else {
            $check = 0004;
        }

        $canReadAsset = (bool) ($this->mode & $check);

        if (null !== $this->parent) {
            $canReadAsset = $canReadAsset && $this->parent->isReadable($uid, $gid);
        }

        return $canReadAsset;
    }

    /**
     * Check if the asset is writable
     */
    public function isWritable(int $uid, int $gid): bool
    {
        if (0 === $uid || 0 === $gid) {
            return true;
        }

        if ($this->uid === $uid) {
            $check = 0200;
        } elseif ($this->gid === $gid) {
            $check = 0020;
        } else {
            $check = 0002;
        }

        return (bool) ($this->mode & $check);
    }

    /**
     * Check if the asset is executable
     */
    public function isExecutable(int $uid, int $gid): bool
    {
        if ($this->uid === $uid) {
            $check = 0100;
        } elseif ($this->gid === $gid) {
            $check = 0010;
        } else {
            $check = 0001;
        }

        return (bool) ($this->mode & $check);
    }

    /**
     * Check if the asset is owned by a specific user
     */
    public function isOwnedByUser(int $uid): bool
    {
        return $uid === $this->uid;
    }
}
