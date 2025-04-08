<?php declare(strict_types=1);
namespace TestFs;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TestFs\Exception\BuildFromDirectoryException;
use TestFs\Exception\InsufficientStorageException;

use function file_get_contents as php_file_get_contents;
use function is_dir as php_is_dir;

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

    /**
     * Return a string representing the contents of the device
     */
    public function tree(): string
    {
        return $this->root->tree();
    }

    /**
     * Shrink the device size to fit the current contents
     */
    public function shrinkToFit(): void
    {
        $this->size = $this->root->getSize();
    }

    /**
     * Mirror a local directory into the virtual filesystem
     *
     * This method can be used to build up a virtual filesystem based on a local path. Existing
     * contents in the virtual filesystem will be overwritten. The directory specified in $path
     * will act as the root of the modified filesystem.
     *
     * @throws BuildFromDirectoryException
     */
    public function buildFromDirectory(string $path, bool $includeFileContents = false): void
    {
        $realPath = realpath($path);

        if (false === $realPath) {
            throw new BuildFromDirectoryException(sprintf('Path "%s" does not exist', $path));
        }

        if (!php_is_dir($realPath)) {
            throw new BuildFromDirectoryException(sprintf('Path "%s" is not a directory', $realPath));
        }

        $prefixLength = strlen($realPath);
        $trimPath = fn (string $path): string => substr($path, $prefixLength + 1);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $this->root = new RootDirectory($this);

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $dirName = $trimPath($file->getRealpath());

                if ('' !== $dirName && !is_dir($dirName)) {
                    if (!mkdir($dirName, $file->getPerms() & 0777)) {
                        throw new BuildFromDirectoryException(sprintf(
                            'Failed to create directory "%s" in the virtual file system',
                            $dirName,
                        ));
                    }
                }

                continue;
            }

            $filePath = $trimPath($file->getRealpath());

            $contents = '';
            if ($includeFileContents) {
                $contents = php_file_get_contents($file->getRealpath());
                if (false === $contents) {
                    throw new BuildFromDirectoryException(sprintf(
                        'Failed to read contents of "%s"',
                        $file->getRealpath(),
                    ));
                }
            }

            if (false === file_put_contents($filePath, $contents)) {
                throw new BuildFromDirectoryException(sprintf(
                    'Failed to write contents of "%s" to the virtual file system',
                    $filePath,
                ));
            }

            if (false === chmod($filePath, $file->getPerms() & 0777)) {
                throw new BuildFromDirectoryException(sprintf(
                    'Failed to set permissions of "%s" in the virtual file system',
                    $filePath,
                ));
            }
        }
    }
}
