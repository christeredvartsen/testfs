<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidWhenceException;

class File extends Asset
{
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
    private ?string $exclusiveLock = null;

    /**
     * Resources who has a shared lock, IDs as key
     *
     * @var array<string,true>
     */
    private array $sharedLocks = [];

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

    public function getType(): int
    {
        return self::TYPE_FILE;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    protected function getDefaultMode(): int
    {
        return 0644;
    }

    /**
     * Create a new file
     */
    public function __construct(string $name, private string $contents = '')
    {
        parent::__construct($name);
        $this->size = strlen($contents);
    }

    /**
     * Set read flag
     */
    public function setRead(bool $read): void
    {
        $this->read = $read;
    }

    /**
     * Set write flag
     */
    public function setWrite(bool $write): void
    {
        $this->write = $write;
    }

    /**
     * Get file contents
     *
     * Using this method does not touch the internal offset or access timestamp.
     */
    public function getContents(): string
    {
        return $this->contents;
    }

    /**
     * Get the current offset
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Read an amount of bytes from the current offset, and update the offset
     *
     * This method also updates the access time of the file.
     */
    public function read(int $bytes): string
    {
        if ($this->append || !$this->read) {
            return '';
        }

        $this->updateLastAccessed();
        $data = substr($this->contents, $this->offset, $bytes);
        $this->offset += strlen($data);

        return $data;
    }

    /**
     * Check if the offset is at the end of file (EOF)
     */
    public function eof(): bool
    {
        return !$this->append && ($this->offset >= $this->size);
    }

    /**
     * Write data at the current offset, update the offset and return the number of bytes written
     *
     * This method will also update the file modification time.
     */
    public function write(string $data): int
    {
        if (!$this->write) {
            return 0;
        }

        if ($this->append) {
            $this->offset = $this->size;
        }

        if ($this->size < $this->offset) {
            // Size of the file can be less than the current offset after a truncate operation, as
            // that operation does not reset the pointer. Pad with null bytes
            $this->contents = str_pad($this->contents, $this->offset, "\0", STR_PAD_RIGHT);
            $this->size = $this->offset;
        }

        $device = $this->getDevice();
        $len = strlen($data);

        if (false === $device?->canFitBytes($len)) {
            trigger_error('fwrite(): write failed, no space left on device', E_USER_NOTICE);

            $availableSize = $device->getAvailableSize();
            $data = substr($data, 0, $availableSize);
            $len = $availableSize;
        }

        $this->updateLastModified();
        $this->contents = substr_replace($this->contents, $data, $this->offset, $len);
        $this->size += $len;
        $this->offset += $len;

        return $len;
    }

    /**
     * Truncate the file to a given length
     *
     * This method will also update the file modification time. The internal offset is not updated.
     */
    public function truncate(int $size = 0): bool
    {
        if (!$this->write) {
            return false;
        }

        if ($size > $this->size) {
            $this->contents = str_pad($this->contents, $size, "\0", STR_PAD_RIGHT);
        } else {
            $this->contents = substr($this->contents, 0, $size);
        }

        $this->updateLastModified();
        $this->size = $size;

        return true;
    }

    /**
     * Rewind the offset to the start of the file
     */
    public function rewind(): void
    {
        $this->seek(0, SEEK_SET);
    }

    /**
     * Forward the offset to EOF
     */
    public function forward(): void
    {
        $this->seek(0, SEEK_END);
    }

    /**
     * Set the internal offset
     *
     * If the offset is beyond EOF, fill the gap with \0. If this is the case, also update the file modification time.
     *
     * @throws InvalidWhenceException
     */
    public function seek(int $offset, int $whence = SEEK_SET): bool
    {
        if ($this->append) {
            return false;
        }

        $this->offset = match($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->offset + $offset,
            SEEK_END => $this->size + $offset,
            default  => throw new InvalidWhenceException($whence),
        };

        if ($this->offset > $this->size) {
            $this->contents .= str_repeat("\0", $this->offset - $this->size);
            $this->size = $this->offset;
            $this->updateLastModified();
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
     */
    public function lock(string $id, int $operation): bool
    {
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

            $this->exclusiveLock = $id;
        } elseif (LOCK_SH === $operation) {
            if ($this->hasExclusiveLock()) {
                return false;
            }

            $this->sharedLocks[$id] = true;
        }

        return true;
    }

    /**
     * Check for an exclusive lock
     */
    public function hasExclusiveLock(?string $id = null): bool
    {
        if (null === $id) {
            return null !== $this->exclusiveLock;
        }

        return $id === $this->exclusiveLock;
    }

    /**
     * Check for a shared lock
     */
    public function hasSharedLock(?string $id = null): bool
    {
        if (null === $id) {
            return !empty($this->sharedLocks);
        }

        return array_key_exists($id, $this->sharedLocks);
    }

    /**
     * Check for a file lock
     */
    public function isLocked(string $id = null): bool
    {
        return $this->hasExclusiveLock($id) || $this->hasSharedLock($id);
    }

    /**
     * Unlock the file
     */
    public function unlock(string $id): void
    {
        if ($id === $this->exclusiveLock) {
            $this->exclusiveLock = null;
        }

        unset($this->sharedLocks[$id]);
    }

    /**
     * Set append mode
     */
    public function setAppendMode(bool $append): void
    {
        $this->append = $append;
    }

    /**
     * Get the append mode
     */
    public function getAppendMode(): bool
    {
        return $this->append;
    }
}
