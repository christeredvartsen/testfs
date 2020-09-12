<?php declare(strict_types=1);
namespace TestFs;

class File extends Asset {
    /**
     * Contents of the file
     */
    private string $content = '';

    /**
     * File size
     */
    private int $size = 0;

    /**
     * Current offset in the file
     */
    private int $offset = 0;

    /**
     * Identifier of the resource who owns the exclusive lock
     */
    private ?string $lockEx = null;

    /**
     * A list of resources who has a shared lock, IDs as key
     *
     * @var array<string,bool>
     */
    private array $lockSh = [];

    /**
     * Whether or not the file was opened with mode 'a'
     */
    private bool $append = false;

    /**
     * Whether or not the file has been opened for reading
     */
    private bool $read = true;

    /**
     * Whether or not the file has been opened for writing
     */
    private bool $write = true;

    /**
     * Class constructor
     *
     * @param string $name The name of the file
     * @param string $content The content of the file
     */
    public function __construct(string $name, string $content = '') {
        parent::__construct($name);

        $this->content = $content;
        $this->size = strlen($content);
    }

    /**
     * Get the asset type
     *
     * @return int
     */
    public function getType() : int {
        return 0100000;
    }

    /**
     * Set read flag
     *
     * @param bool $read Read flag
     * @return void
     */
    public function setRead(bool $read) : void {
        $this->read = $read;
    }

    /**
     * Set write flag
     *
     * @param bool $write Write flag
     * @return void
     */
    public function setWrite(bool $write) : void {
        $this->write = $write;
    }

    /**
     * Get the default mode
     *
     * @return int
     */
    protected function getDefaultMode() : int {
        return 0644;
    }

    /**
     * Get file content
     *
     * Using this method does not touch the internal offset or access timestamp.
     *
     * @return string
     */
    public function getContent() : string {
        return $this->content;
    }

    /**
     * Get file size
     *
     * @return int
     */
    public function getSize() : int {
        return $this->size;
    }

    /**
     * Get the offset
     *
     * @return int
     */
    public function getOffset() : int {
        return $this->offset;
    }

    /**
     * Read an amount of bytes from the current offset, and update the offset
     *
     * This method also updates the access time of the file.
     *
     * @param int $count Number of bytes to read
     * @return string Returns the data read
     */
    public function read(int $count) : string {
        if ($this->append || !$this->read) {
            return '';
        }

        $this->atime = time();
        $data = substr($this->content, $this->offset, $count);
        $this->offset += strlen($data);

        return $data;
    }

    /**
     * Check if the offset is at the end of file (EOF)
     *
     * @return bool
     */
    public function eof() : bool {
        return !$this->append && ($this->offset >= $this->size);
    }

    /**
     * Write data at the current offset, and update the offset
     *
     * This method will also update the file modification time.
     *
     * @param string $data The data to write
     * @return int Returns numbers of bytes written
     */
    public function write(string $data) : int {
        if (!$this->write) {
            return 0;
        }

        if ($this->append) {
            $this->offset = $this->size;
        }

        if ($this->size < $this->offset) {
            // Size of the file can be less than the current offset after a truncate operation, as
            // that operation does not reset the pointer. Pad with null bytes
            $this->content = str_pad($this->content, $this->offset, "\0", STR_PAD_RIGHT);
            $this->size = $this->offset;
        }

        $len = strlen($data);
        $this->mtime = time();

        $this->content = substr_replace($this->content, $data, $this->offset, $len);

        $this->size += $len;
        $this->offset += $len;

        return $len;
    }

    /**
     * Truncate the file to a given length
     *
     * This method will also update the file modification time. The internal offset is not updated.
     *
     * @param int $size The size to truncate to
     * @return bool
     */
    public function truncate(int $size = 0) : bool {
        if (!$this->write) {
            return false;
        }

        if ($size > $this->size) {
            $this->content = str_pad($this->content, $size, "\0", STR_PAD_RIGHT);
        } else {
            $this->content = substr($this->content, 0, $size);
        }

        $this->mtime = time();
        $this->size = $size;

        return true;
    }

    /**
     * Rewind the offset to the start of the file
     *
     * @return void
     */
    public function rewind() : void {
        $this->seek(0, SEEK_SET);
    }

    /**
     * Forward the offset to EOF
     *
     * @return void
     */
    public function forward() : void {
        $this->seek(0, SEEK_END);
    }

    /**
     * Set the internal offset
     *
     * If the offset is beyond EOF, fill the gap with \0. If this is the case, also update the file modification time.
     *
     * @param int $offset The offset to set
     * @param int $whence From where to set the offset
     * @return bool
     */
    public function seek(int $offset, int $whence = SEEK_SET) : bool {
        if ($this->append) {
            return false;
        }

        switch ($whence) {
            case SEEK_SET: $this->offset = $offset; break;
            case SEEK_CUR: $this->offset += $offset; break;
            case SEEK_END: $this->offset = $this->size + $offset; break;
        }

        if ($this->offset > $this->size) {
            $this->content .= str_repeat("\0", $this->offset - $this->size);
            $this->size = $this->offset;
            $this->mtime = time();
        }

        return true;
    }

    /**
     * Lock the file
     *
     * The following operations are supported:
     *
     * - LOCK_EX
     * - LOCK_SH
     * - LOCK_UN
     *
     * Operations with LOCK_NB are not supported.
     *
     * @param string $id ID of the resource who calls this method
     * @param int $operation Locking operation
     * @return bool
     */
    public function lock(string $id, int $operation) : bool {
        if (!in_array($operation, [LOCK_EX, LOCK_SH, LOCK_UN])) {
            return false;
        }

        // Unlock for this specific ID as acquiring a new lock will override the existing lock the
        // ID might have
        $this->unlock($id);

        if (LOCK_EX === $operation) {
            if ($this->isLocked()) {
                return false;
            }

            $this->lockEx = $id;
        } else if (LOCK_SH === $operation) {
            if ($this->hasExclusiveLock()) {
                return false;
            }

            $this->lockSh[$id] = true;
        }

        return true;
    }

    /**
     * Check for an exclusive lock
     *
     * @param string $id Specific ID to check for
     * @return bool
     */
    public function hasExclusiveLock(string $id = null) : bool {
        if (null === $id) {
            return null !== $this->lockEx;
        }

        return $id === $this->lockEx;
    }

    /**
     * Check for a shared lock
     *
     * @param string $id The ID to check for
     * @return bool
     */
    public function hasSharedLock(string $id = null) : bool {
        if (null === $id) {
            return !empty($this->lockSh);
        }

        return !empty($this->lockSh[$id]) && true === $this->lockSh[$id];
    }

    /**
     * Check for a file lock
     *
     * @param string $id The ID to check for
     * @return bool
     */
    public function isLocked(string $id = null) : bool {
        return $this->hasExclusiveLock($id) || $this->hasSharedLock($id);
    }

    /**
     * Unlock the file
     *
     * @param string $id The ID to unlock for
     * @return bool
     */
    public function unlock(string $id) : bool {
        if ($id === $this->lockEx) {
            $this->lockEx = null;
        }

        unset($this->lockSh[$id]);

        return true;
    }

    /**
     * Set append mode
     *
     * @param bool $append
     * @return void
     */
    public function setAppendMode(bool $append) : void {
        $this->append = $append;
    }

    /**
     * Get the append mode
     *
     * @return bool
     */
    public function getAppendMode() : bool {
        return $this->append;
    }
}
