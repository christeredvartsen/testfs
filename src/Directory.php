<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\DuplicateAssetException;
use TestFs\Exception\InsufficientStorageException;
use TestFs\Exception\UnknownAssetException;

class Directory extends Asset
{
    /**
     * Child assets
     *
     * Keys are the asset names
     *
     * @var list<Asset>
     */
    private array $children = [];

    public function getType(): int
    {
        return self::TYPE_DIRECTORY;
    }

    public function getSize(): int
    {
        $size = 0;

        foreach ($this->children as $child) {
            $size += $child->getSize();
        }

        return $size;
    }

    protected function getDefaultMode(): int
    {
        return 0777;
    }

    /**
     * Get directory children
     *
     * @return list<Asset>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Check if a directory is empty or not
     */
    public function isEmpty(): bool
    {
        return 0 === count($this->children);
    }

    /**
     * Check if the directory has a child asset with the specified name
     */
    public function hasChild(string $name): bool
    {
        foreach ($this->children as $child) {
            if ($name === $child->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the directory has a child asset
     */
    public function hasAsset(Asset $asset): bool
    {
        foreach ($this->children as $child) {
            if ($asset === $child) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the directory has a child file with the specified name
     */
    public function hasFile(string $name): bool
    {
        return $this->getChild($name) instanceof File;
    }

    /**
     * Check if the directory has a child directory with the given name
     */
    public function hasDirectory(string $name): bool
    {
        return $this->getChild($name) instanceof Directory;
    }

    /**
     * Get a child asset by its name
     */
    public function getChild(string $name): ?Asset
    {
        foreach ($this->children as $child) {
            if ($name === $child->getName()) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Get a child file by its name
     */
    public function getFile(string $name): ?File
    {
        $file = $this->getChild($name);

        return $file instanceof File ? $file : null;
    }

    /**
     * Get a child directory by its name
     */
    public function getDirectory(string $name): ?Directory
    {
        $dir = $this->getChild($name);

        return $dir instanceof Directory ? $dir : null;
    }

    /**
     * Add a child asset to the directory
     *
     * If the child alread has a parent it will detach itself before attaching to the new parent.
     *
     * Adding an already existing child is a no-op.
     *
     * @throws InsufficientStorageException
     * @throws DuplicateAssetException
     */
    public function addChild(Asset $asset): void
    {
        if ($this->hasAsset($asset)) {
            return;
        }

        $device = $this->getDevice();

        if (false === $device?->canFitAsset($asset)) {
            throw new InsufficientStorageException($device->getAvailableSize(), $asset->getSize());
        }

        if ($this->hasChild($asset->getName())) {
            throw new DuplicateAssetException($this, $asset);
        }

        $asset->detach();
        $asset->setParent($this);

        $this->children[] = $asset;
    }

    /**
     * Remove a child by its name

     * @throws UnknownAssetException
     */
    public function removeChild(string $name): void
    {
        if (!$this->hasChild($name)) {
            throw new UnknownAssetException($name);
        }

        $this->children = array_values(array_filter(
            $this->children,
            fn (Asset $asset): bool => $name !== $asset->getName(),
        ));
    }

    /**
     * Return a string representing the directory and its contents, like the tree command
     */
    public function tree(): string
    {
        return $this->generateTree();
    }

    /**
     * Generate a tree representation of the directory and its contents, recursively
     *
     * @param array<int,bool> $prefix
     */
    private function generateTree(array $prefix = [], int &$numFiles = 0, int &$numDirectories = 0): string
    {
        $outer = empty($prefix);
        $output = [$this->getName()];
        $children = $this->children;

        usort($children, fn (Asset $a, Asset $b): int => strcmp($a->getName(), $b->getName()));

        $numChildren = count($children);
        $prefixIndex = count($prefix);
        $i = 0;
        $p = implode('', array_map(
            fn (bool $hasMore): string => $hasMore ? '│   ' : '    ',
            $prefix,
        ));

        foreach ($children as $asset) {
            $last = ++$i === $numChildren;
            $prefix[$prefixIndex] = !$last && (1 < $numChildren);

            if ($asset instanceof Directory) {
                $numDirectories++;
                $child = $asset->generateTree($prefix, $numFiles, $numDirectories);
            } else {
                $numFiles++;
                $child = $asset->getName();
            }

            $output[] = $p . ($last ? '└── ' : '├── ') . $child;
        }

        if ($outer) {
            $output[] = null;
            $output[] = sprintf(
                '%d director%s, %d file%s',
                $numDirectories,
                1 === $numDirectories ? 'y' : 'ies',
                $numFiles,
                1 === $numFiles ? '' : 's',
            );
        }

        return implode(PHP_EOL, $output);
    }
}
