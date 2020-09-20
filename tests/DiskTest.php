<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass TestFs\Disk
 */
class DiskTest extends TestCase {
    /**
     * @covers ::getDiskSize
     * @covers ::setDiskSize
     */
    public function testCanSetAndGetDiskSize() : void {
        $dir = new Disk('name');
        $this->assertSame(Disk::UNLIMITED_DISK_SIZE, $dir->getDiskSize(), 'Unexpected default disk size');
        $dir->setDiskSize(123);
        $this->assertSame(123, $dir->getDiskSize(), 'Expected disk size to be updated');
    }

    /**
     * @covers ::setDiskSize
     */
    public function testThrowsExceptionWhenSettingSizeTooSmall() : void {
        $dir = new Disk('name');
        $dir->addChild(new File('name', 'this is my content'));

        $this->expectExceptionObject(new InvalidArgumentException('Size of the files in the virtual filesystem already exceeds the given size'));
        $dir->setDiskSize(2);
    }

    /**
     * @covers ::getAvailableSize
     */
    public function testCanGetAvailableSize() : void {
        $dir = new Disk('name');
        $this->assertSame(Disk::UNLIMITED_DISK_SIZE, $dir->getAvailableSize(), 'Unexpected available disk size');
        $dir->setDiskSize(20);
        $dir->addChild(new File('name', 'this is my content'));
        $this->assertSame(2, $dir->getAvailableSize(), 'Unexpected available disk size');
    }
}