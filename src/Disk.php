<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;

class Disk extends Directory {
    const UNLIMITED_DISK_SIZE = -1;

    /**
     * Available storage space
     */
    private int $diskSize = self::UNLIMITED_DISK_SIZE;

    /**
     * Set the disk size in bytes
     *
     * @param int $size Size in bytes
     * @throws InvalidArgumentException
     * @return void
     */
    public function setDiskSize(int $size) : void {
        if ($size < $this->getSize()) {
            throw new InvalidArgumentException('Size of the files in the virtual filesystem already exceeds the given size');
        }

        $this->diskSize = max(self::UNLIMITED_DISK_SIZE, $size);
    }

    /**
     * Get the disk size
     *
     * @return int
     */
    public function getDiskSize() : int {
        return $this->diskSize;
    }

    /**
     * Get the available size
     *
     * @return int
     */
    public function getAvailableSize() : int {
        if (self::UNLIMITED_DISK_SIZE === $this->diskSize) {
            return self::UNLIMITED_DISK_SIZE;
        }

        return $this->diskSize - $this->getSize();
    }

    /**
     * Return self when getting the root directory
     *
     * @return Disk
     */
    public function getDisk(): Disk {
        return $this;
    }
}
