<?php declare(strict_types=1);
namespace TestFs;

/**
 * @codeCoverageIgnore
 */
class AssetFactory {
    /**
     * Create a directory asset
     *
     * @param string $name Name of the directory
     * @return Directory
     */
    public function directory(string $name) : Directory {
        return new Directory($name);
    }

    /**
     * Create a file asset
     *
     * @param string $name Name of the file
     * @param string $content Content of the file
     * @return File
     */
    public function file(string $name, string $content = '') : File {
        return new File($name, $content);
    }
}
