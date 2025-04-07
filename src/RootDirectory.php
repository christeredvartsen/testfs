<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\RuntimeException;

class RootDirectory extends Directory
{
    public function __construct(private Device $device)
    {
        parent::__construct('/');
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function setParent(Directory $parent, bool $addAsChild = true): void
    {
        throw new RuntimeException('The root directory can not have a parent');
    }

    public function getParent(): null
    {
        return null;
    }

    public function setName(string $name): void
    {
    }

    public function getName(): string
    {
        return '/';
    }
}
