<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;

class Directory extends Asset {
    /**
     * Asset type
     *
     * @var int
     */
    protected $type = 0040000;

    /**
     * Child assets in a numerically indexed array
     *
     * @var array
     */
    private $children = [];

    /**
     * Get the default mode
     *
     * @return int
     */
    protected function getDefaultMode() : int {
        return 0777;
    }

    /**
     * Get directory children
     *
     * @return File[]|Directory[] Returns a numerically indexed array with all child assets of the directory
     */
    public function getChildren() : array {
        return $this->children;
    }

    /**
     * Check if a directory is empty or not
     *
     * @return bool True if the directory is empty, false otherwise
     */
    public function isEmpty() : bool {
        return 0 === count($this->children);
    }

    /**
     * Check if the directory has a child
     *
     * @param string $name The name of the child
     * @return bool True if a child with the given name exists, false otherwise
     */
    public function hasChild(string $name) : bool {
        return null !== $this->getChild($name);
    }

    /**
     * Check if the directory has a child file
     *
     * @param string $name The name of the file
     * @return bool True if a child file with the given name exists, false otherwise
     */
    public function hasFile(string $name) : bool {
        return $this->getChild($name) instanceof File;
    }

    /**
     * Check if the directory has a child directory
     *
     * @param string $name The name of the directory
     * @return bool True if a child directory with the given name exists, false otherwise
     */
    public function hasDirectory(string $name) : bool {
        return $this->getChild($name) instanceof Directory;
    }

    /**
     * Get a child
     *
     * @param string $name The name of the child to get
     * @return File|Directory|null Returns the asset if it exists
     */
    public function getChild(string $name) {
        $children = array_filter($this->children, function(Asset $a) use ($name) : bool {
            return $a->getName() === $name;
        });

        return empty($children) ? null : current($children);
    }

    /**
     * Add a child asset to the directory, and set this directory as the parent of the asset
     *
     * @param Asset $asset The child to add
     * @throws InvalidArgumentException Throws an exception if a child with the same name exists
     * @return void
     */
    public function addChild(Asset $asset) : void {
        $name = $asset->getName();

        if ($this->hasChild($name)) {
            throw new InvalidArgumentException(sprintf('A child with the name "%s" already exists', $name));
        }

        if ($asset->getParent() !== $this) {
            $asset->setParent($this, false);
        }

        $this->children[] = $asset;
    }

    /**
     * Remove a child
     *
     * @param string $name The name of the child to remove
     * @throws InvalidArgumentException Throws an exception if the child does not exist
     * @return void
     */
    public function removeChild(string $name) : void {
        if (!$this->hasChild($name)) {
            throw new InvalidArgumentException(sprintf('Child "%s" does not exist', $name));
        }

        $this->children = array_values(array_filter($this->children, function(Asset $child) use ($name) : bool {
            return $child->getName() !== $name;
        }));
    }

    /**
     * Get the size of the directory
     *
     * @return int Size in bytes
     */
    public function getSize() : int {
        $size = 0;

        foreach ($this->children as $childAsset) {
            $size += $childAsset->getSize();
        }

        return $size;
    }

    /**
     * Return a string representing the directory and its contents, like the tree command
     *
     * @param array $prefix Prefix data for the tree
     * @return string
     */
    public function tree(array $prefix = [], int &$numFiles = 0, int &$numDirectories = 1) : string {
        $first = empty($prefix);
        $name = str_replace(StreamWrapper::getRootDirectoryName(), '', $this->getName());
        $output = [sprintf('%s%s', $name, $first ? '/' : '')];
        $children = $this->children;

        usort($children, function(Asset $a, Asset $b) : int {
            return strcmp($a->getName(), $b->getName());
        });

        $numChildren = count($children);
        $prefixIndex = count($prefix);
        $i = 0;
        $p = implode('', array_map(function(bool $hasMore) : string {
            return $hasMore ? '│   ' : '    ';
        }, $prefix));

        foreach ($children as $asset) {
            $last = ++$i === $numChildren;
            $prefix[$prefixIndex] = !$last && (1 < $numChildren);

            if ($asset instanceof Directory) {
                $numDirectories++;
                $child = $asset->tree($prefix, $numFiles, $numDirectories);
            } else {
                $numFiles++;
                $child = $asset->getName();
            }

            $output[] = $p . ($last ? '└── ' : '├── ') . $child;
        }

        if ($first) {
            $output[] = null;
            $output[] = sprintf(
                '%d director%s, %d file%s',
                $numDirectories,
                1 === $numDirectories ? 'y' : 'ies',
                $numFiles,
                1 === $numFiles ? '' : 's'
            );
        }

        return implode(PHP_EOL, $output);
    }
}
