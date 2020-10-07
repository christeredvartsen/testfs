<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;

class Device extends Directory {
    const UNLIMITED_SIZE = -1;

    /**
     * Available storage space
     */
    private int $deviceSize = self::UNLIMITED_SIZE;

    /**
     * Set the device size in bytes
     *
     * @param int $size Size in bytes
     * @throws InvalidArgumentException
     * @return void
     */
    public function setDeviceSize(int $size) : void {
        if ($size < $this->getSize()) {
            throw new InvalidArgumentException('Size of the files in the virtual filesystem already exceeds the given size');
        }

        $this->deviceSize = max(self::UNLIMITED_SIZE, $size);
    }

    /**
     * Get the device size
     *
     * @return int
     */
    public function getDeviceSize() : int {
        return $this->deviceSize;
    }

    /**
     * Get the available size left on the device
     *
     * @return int
     */
    public function getAvailableSize() : int {
        if (self::UNLIMITED_SIZE === $this->deviceSize) {
            return self::UNLIMITED_SIZE;
        }

        return $this->deviceSize - $this->getSize();
    }

    /**
     * Return self when getting the root directory
     *
     * @return Device
     */
    public function getDevice(): Device {
        return $this;
    }
}
