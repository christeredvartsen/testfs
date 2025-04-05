<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\Attributes\CoversClass;
use TestFs\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

#[CoversClass(Device::class)]
class DeviceTest extends TestCase {
    public function testCanSetAndGetSize() : void {
        $dir = new Device('name');
        $this->assertSame(Device::UNLIMITED_SIZE, $dir->getDeviceSize(), 'Unexpected default size');
        $dir->setDeviceSize(123);
        $this->assertSame(123, $dir->getDeviceSize(), 'Expected size to be updated');
    }

    public function testThrowsExceptionWhenSettingSizeTooSmall() : void {
        $dir = new Device('name');
        $dir->addChild(new File('name', 'this is my content'));

        $this->expectExceptionObject(new InvalidArgumentException('Size of the files in the virtual filesystem already exceeds the given size'));
        $dir->setDeviceSize(2);
    }

    public function testCanGetAvailableSize() : void {
        $dir = new Device('name');
        $this->assertSame(Device::UNLIMITED_SIZE, $dir->getAvailableSize(), 'Unexpected available size');
        $dir->setDeviceSize(20);
        $dir->addChild(new File('name', 'this is my content'));
        $this->assertSame(2, $dir->getAvailableSize(), 'Unexpected available size');
    }
}
