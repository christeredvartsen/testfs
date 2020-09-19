<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;

class RootDirectory extends Directory {
    /**
     * Available storage space
     */
    private int $diskSize = -1;

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

        $this->diskSize = max(-1, $size);
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
        if (-1 === $this->diskSize) {
            return -1;
        }

        return $this->diskSize - $this->getSize();
    }

    /**
     * Return self when getting the root directory
     *
     * @return RootDirectory
     */
    public function getRootDirectory(): RootDirectory {
        return $this;
    }
}
