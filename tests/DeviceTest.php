<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TestFs\Exception\BuildFromDirectoryException;
use TestFs\Exception\InsufficientStorageException;

#[CoversClass(Device::class)]
class DeviceTest extends TestCase
{
    private Device $device;

    protected function setUp(): void
    {
        if (!StreamWrapper::register()) {
            $this->fail('Unable to register streamwrapper');
        }

        $device = StreamWrapper::getDevice();

        if (null === $device) {
            $this->fail('Wrapper has not been properly initialized');
        }

        $this->device = $device;
    }

    protected function tearDown(): void
    {
        StreamWrapper::unregister();
    }

    public function testCreateDeviceAndCheckSizes(): void
    {
        $device = new Device(size: 123);
        $device
            ->getRoot()
            ->addChild(
                new File('name', 'this is my content'),
            );

        $this->assertSame(123, $device->getSize(), 'Unexpected device size');
        $this->assertSame(105, $device->getAvailableSize(), 'Unexpected available device size');

        $device->setSize(200);
        $this->assertSame(200, $device->getSize(), 'Unexpected device size');
        $this->assertSame(182, $device->getAvailableSize(), 'Unexpected available device size');

        $this->expectExceptionObject(new InsufficientStorageException(2, 18));
        $device->setSize(2);
    }

    public function testCreateDeviceAndCheckSizesWithUnlimitedSize(): void
    {
        $device = new Device();
        $device
            ->getRoot()
            ->addChild(
                new File('name', 'this is my content'),
            );

        $this->assertSame(Device::UNLIMITED_SIZE, $device->getSize(), 'Unexpected device size');
        $this->assertSame(Device::UNLIMITED_SIZE, $device->getAvailableSize(), 'Unexpected available device size');
    }

    public function testCanBuildFromDirectory(): void
    {
        $this->device->buildFromDirectory(FIXTURES_DIR, true);
        $this->assertSame(104, $this->device->getRoot()->getSize(), 'Unexpected root directory size');
        $this->assertSame(
            <<<TREE
            /
            ├── dir
            │   ├── foo.txt
            │   └── sub
            │       └── file.php
            └── file.txt

            2 directories, 3 files
            TREE,
            $this->device->tree(),
        );
    }

    public function testBuildFromDirectoryFailsWhenPointingToAFile(): void
    {
        $this->expectException(BuildFromDirectoryException::class);
        $this->expectExceptionMessageMatches('/^Path .* is not a directory$/');
        $this->device->buildFromDirectory(__FILE__);
    }

    public function testBuildFromDirectoryFailsWhenDirectoryDoesNotExist(): void
    {
        $this->expectException(BuildFromDirectoryException::class);
        $this->expectExceptionMessageMatches('/^Path .* does not exist$/');
        $this->device->buildFromDirectory(__DIR__ . '/non-existing-directory');
    }
}
