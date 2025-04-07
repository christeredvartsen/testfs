<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InsufficientStorageException;

class Device
{
    public const UNLIMITED_SIZE = -1;

    private RootDirectory $root;

    public function __construct(private int $size = self::UNLIMITED_SIZE)
    {
        $this->root = new RootDirectory($this);
    }

    /**
     * Set the device size in bytes
     *
     * @throws InsufficientStorageException
     */
    public function setSize(int $size): void
    {
        if ($size !== self::UNLIMITED_SIZE && $size < $this->root->getSize()) {
            throw new InsufficientStorageException($size, $this->root->getSize());
        }

        $this->size = $size;
    }

    /**
     * Get the device size
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the available size left on the device
     */
    public function getAvailableSize(): int
    {
        if (self::UNLIMITED_SIZE === $this->size) {
            return self::UNLIMITED_SIZE;
        }

        return $this->size - $this->root->getSize();
    }

    /**
     * Whether or not the device has enough space to fit a number of bytes
     */
    public function canFitBytes(int $bytes): bool
    {
        if (self::UNLIMITED_SIZE === $this->size) {
            return true;
        }

        return $bytes <= $this->getAvailableSize();
    }

    /**
     * Whether or not the device has enough space to fit an asset
     */
    public function canFitAsset(Asset $asset): bool
    {
        return $this->canFitBytes($asset->getSize());
    }

    /**
     * Get the root directory
     */
    public function getRoot(): RootDirectory
    {
        return $this->root;
    }
}
