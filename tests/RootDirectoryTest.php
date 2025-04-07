<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TestFs\Exception\RuntimeException;

#[CoversClass(RootDirectory::class)]
class RootDirectoryTest extends TestCase
{
    public function testNameIsImmutable(): void
    {
        $directory = new RootDirectory(new Device());
        $this->assertSame('/', $directory->getName());
        $directory->setName('foobar');
        $this->assertSame('/', $directory->getName());
    }

    public function testCanNotSetParent(): void
    {
        $this->expectExceptionObject(new RuntimeException('The root directory can not have a parent'));
        (new RootDirectory(new Device()))->setParent(new Directory('some name'));
    }

    public function testCanGetDevice(): void
    {
        $device = new Device();
        $directory = new RootDirectory($device);
        $this->assertSame($device, $directory->getDevice());
    }
}
