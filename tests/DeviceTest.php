<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TestFs\Exception\InsufficientStorageException;

#[CoversClass(Device::class)]
class DeviceTest extends TestCase
{
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
}
