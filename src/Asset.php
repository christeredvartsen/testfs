<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;
use TestFs\Exception\RuntimeException;

abstract class Asset {
    private string $name;
    private ?Directory $parent = null;
    protected int $atime;
    protected int $mtime;
    protected int $ctime;
    private int $uid;
    private int $gid;
    protected int $mode;

    /**
     * Class constructor
     *
     * @param string $name The name of the asset
     */
    public function __construct(string $name) {
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
     * @return int
     */
    abstract public function getType() : int;

    /**
     * Get the size of the asset, including child assets
     *
     * @return int
     */
    abstract public function getSize() : int;

    /**
     * Get the default mode of the asset
     *
     * @return int
     */
    abstract protected function getDefaultMode() : int;

    /**
     * Remove the asset
     *
     * @throws RuntimeException Throws an exception if the file does not have a parent
     * @return void
     */
    public function delete() : void {
        $parent = $this->getParent();

        if (null === $parent) {
            throw new RuntimeException('The asset does not have a parent');
        }

        $parent->removeChild($this->getName());
    }

    /**
     * Get last acceessed timestamp
     *
     * @return int
     */
    public function getLastAccessed() : int {
        return $this->atime;
    }

    /**
     * Set the last accessed timestamp
     *
     * @param int $atime The timestamp to set
     */
    public function updateLastAccessed(int $atime = 0) : void {
        if (!$atime) {
            $atime = time();
        }

        $this->atime = $atime;
    }

    /**
     * Get last modified timestamp
     *
     * @return int
     */
    public function getLastModified() : int {
        return $this->mtime;
    }

    /**
     * Set the last modification timestamp
     *
     * @param int $mtime The timestamp to set
     */
    public function updateLastModified(int $mtime = 0) : void {
        if (!$mtime) {
            $mtime = time();
        }

        $this->mtime = $mtime;
    }

    /**
     * Get last inode change timestamp
     *
     * @return int
     */
    public function getLastMetadataModified() : int {
        return $this->ctime;
    }

    /**
     * Set the last inode change timestamp
     *
     * @param int $ctime The timestamp to set
     */
    public function updateLastMetadataModified(int $ctime = 0) : void {
        if (!$ctime) {
            $ctime = time();
        }

        $this->ctime = $ctime;
    }

    /**
     * Set the UID of the asset
     *
     * @param int $uid The UID to set
     * @return void
     */
    public function setUid(int $uid) : void {
        $this->uid = $uid;
        $this->updateLastMetadataModified();
    }

    /**
     * Set the GID of the asset
     *
     * @param int $gid The GID to set
     * @return void
     */
    public function setGid(int $gid) : void {
        $this->gid = $gid;
        $this->updateLastMetadataModified();
    }

    /**
     * Get the UID
     *
     * @return int
     */
    public function getUid() : int {
        return $this->uid;
    }

    /**
     * Get the GID
     *
     * @return int
     */
    public function getGid() : int {
        return $this->gid;
    }

    /**
     * Set the name of the asset
     *
     * @param string $name The name to set
     * @throws InvalidArgumentException
     * @return void
     */
    public function setName(string $name) : void {
        $name = trim($name);

        if (empty($name)) {
            throw new InvalidArgumentException('Name can not be empty');
        } else if (false !== strpos($name, DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Name can not contain a directory separator');
        } else if ($this->parent instanceof Directory && $this->parent->hasChild($name)) {
            throw new InvalidArgumentException('There exists an asset with the same name in this directory');
        }

        $this->name = $name;
    }

    /**
     * Get the asset name
     *
     * @return string
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * Detach from parent
     *
     * @return void
     */
    public function detach() : void {
        if (null === $this->parent) {
            return;
        }

        $this->parent->removeChild($this->getName());
        $this->parent = null;
    }

    /**
     * Set the parent directory
     *
     * @param Directory $parent The parent directory
     * @param bool $addAsChild Whether or not to add the asset as a child to the parent
     * @throws InvalidArgumentException
     * @return void
     */
    public function setParent(Directory $parent, bool $addAsChild = true) : void {
        if ($parent === $this->getParent()) {
            return;
        }

        $name = $this->getName();

        if ($parent->hasChild($name)) {
            throw new InvalidArgumentException(sprintf('Target directory already has a child named "%s"', $name));
        }

        if ($this->parent) {
            $this->parent->removeChild($this->getName());
        }

        $this->parent = $parent;

        if ($addAsChild) {
            $this->parent->addChild($this);
        }
    }

    /**
     * Get the parent directory
     *
     * @return ?Directory
     */
    public function getParent() : ?Directory {
        return $this->parent;
    }

    /**
     * Get the root directory of the file system
     *
     * @return ?RootDirectory
     */
    public function getRootDirectory() : ?RootDirectory {
        return null === $this->parent ? null : $this->parent->getRootDirectory();
    }

    /**
     * Set the mode
     *
     * @param int $mode The mode to set
     * @return void
     */
    public function setMode(int $mode) : void {
        $this->mode = $mode;
    }

    /**
     * Get the mode
     *
     * @return int
     */
    public function getMode() : int {
        return $this->mode;
    }

    /**
     * Check if the asset is readable
     *
     * @param int $uid The UID to check
     * @param int $gid The GID to check
     * @return bool
     */
    public function isReadable(int $uid, int $gid) : bool {
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
     *
     * @param int $uid The UID to check
     * @param int $gid The GID to check
     * @return bool
     */
    public function isWritable(int $uid, int $gid) : bool {
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
     *
     * @param int $uid The UID to check
     * @param int $gid The GID to check
     * @return bool
     */
    public function isExecutable(int $uid, int $gid) : bool {
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
     *
     * @param int $uid The UID to check
     * @return bool
     */
    public function isOwnedByUser(int $uid) : bool {
        return $uid === $this->uid;
    }
}
