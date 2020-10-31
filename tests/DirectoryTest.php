<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TestFs\Exception\NoSpaceLeftOnDeviceException;

/**
 * @coversDefaultClass TestFs\Directory
 */
class DirectoryTest extends TestCase {
    /**
     * @covers ::getType
     */
    public function testCanGetFileType() : void {
        $this->assertSame(0040000, (new Directory('name'))->getType(), 'Incorrect directory type');
    }

    /**
     * @covers ::isEmpty
     * @covers ::getChildren
     */
    public function testIsEmpty() : void {
        $dir = new Directory('name');
        $this->assertSame([], $dir->getChildren(), 'Did not expect any children');
        $this->assertTrue($dir->isEmpty(), 'Expected directory to be empty');
    }

    /**
     * @covers ::addChild
     * @covers ::getChild
     * @covers ::getChildDirectory
     * @covers ::getChildFile
     * @covers ::hasDirectory
     * @covers ::hasFile
     * @covers ::hasChild
     */
    public function testCanAddAndGetChildren() : void {
        $dir = new Directory('name');

        $childDir = $this->createConfiguredMock(Directory::class, ['getName' => 'childDir']);
        $childFile = $this->createConfiguredMock(File::class, ['getName' => 'childFile']);

        $dir->addChild($childDir);
        $dir->addChild($childFile);

        $this->assertSame($childDir, $dir->getChild('childDir'), 'Wrong child directory');
        $this->assertSame($childDir, $dir->getChildDirectory('childDir'), 'Wrong child directory');
        $this->assertSame($childFile, $dir->getChild('childFile'), 'Wrong child file');
        $this->assertSame($childFile, $dir->getChildFile('childFile'), 'Wrong child file');
        $this->assertNull($dir->getChild('foobar'), 'Did not expect to find any child');
        $this->assertTrue($dir->hasDirectory('childDir'), 'Expected directory to have child directory');
        $this->assertFalse($dir->hasDirectory('childFile'), 'Did not expect directory to have child directory');
        $this->assertTrue($dir->hasFile('childFile'), 'Expected directory to have child file');
        $this->assertFalse($dir->hasFile('childDir'), 'Did not expect directory to have child file');
        $this->assertTrue($dir->hasChild('childDir'), 'Expected directory to have child');
        $this->assertTrue($dir->hasChild('childFile'), 'Expected directory to have child');
        $this->assertFalse($dir->hasChild('foobar'), 'Did not expect directory to have child');
    }

    /**
     * @covers ::addChild
     */
    public function testThrowsExceptionWhenAddingChildWithANameThatAlreadyExists() : void {
        $dir = new Directory('name');

        $childDir = $this->createConfiguredMock(Directory::class, ['getName' => 'name']);
        $childFile = $this->createConfiguredMock(File::class, ['getName' => 'name']);

        $dir->addChild($childDir);

        $this->expectExceptionObject(new InvalidArgumentException('A child with the name "name" already exists'));
        $dir->addChild($childFile);
    }

    /**
     * @covers ::removeChild
     */
    public function testCanRemoveChild() : void {
        $dir = new Directory('name');

        $child = $this->createConfiguredMock(Directory::class, ['getName' => 'name']);

        $dir->addChild($child);

        $this->assertTrue($dir->hasChild('name'), 'Expected directory to have child');
        $dir->removeChild('name');
        $this->assertFalse($dir->hasChild('name'), 'Did not expect directory to have child');
    }

    /**
     * @covers ::removeChild
     */
    public function testThrowsExceptionWhenDeletingAChildThatDoesNotExist() : void {
        $dir = new Directory('name');

        $this->expectExceptionObject(new InvalidArgumentException('Child "name" does not exist'));
        $dir->removeChild('name');
    }

    /**
     * @covers ::getSize
     */
    public function testCanGetSize() : void {
        $dir = new Directory('parent');
        $childDir = new Directory('child');
        $file1 = new File('file1', 'some content');
        $file2 = new File('file2', 'some more content');
        $file3 = new File('file3', 'even more content');

        $childDir->addChild($file1);
        $childDir->addChild($file2);
        $dir->addChild($childDir);
        $dir->addChild($file3);

        $this->assertSame(46, $dir->getSize(), 'Incorrect file size');
    }

    /**
     * @covers ::getDefaultMode
     */
    public function testGetDefaultMode() : void {
        $this->assertSame(0777, (new Directory('name'))->getMode(), 'Incorrect mode');
    }

    /**
     * @covers ::tree
     */
    public function testCanGenerateTree() : void {
        $tree = <<<TREE
parent/
├── child dir
│   └── child file of child dir
└── child file

2 directories, 2 files
TREE;

        $parent = new Directory('parent');
        $childDir = new Directory('child dir');
        $childFile = new File('child file');
        $childChildFile = new File('child file of child dir');
        $childDir->addChild($childChildFile);
        $parent->addChild($childDir);
        $parent->addChild($childFile);

        $this->assertSame($tree, $parent->tree(), 'Incorrect tree');
    }

    /**
     * @covers ::addChild
     */
    public function testDirectoryFailsWhenAddingUnsupportedChildAsset() : void {
        $dir = new Directory('name');

        $this->expectExceptionObject(new InvalidArgumentException('Unsupported asset type: TestFs\UnsupportedAsset'));
        $dir->addChild(new UnsupportedAsset('name'));
    }

    /**
     * @covers ::addChild
     */
    public function testThrowsExceptionWhenAddingChildWithNoSpaceLeftOnDevice() : void {
        $device = new Device('name');
        $device->setDeviceSize(1);

        $this->expectExceptionObject(new NoSpaceLeftOnDeviceException('There is not enough space on the device to add the asset, available: 1 byte, asset: 18 bytes'));
        $device->addChild(new File('name', 'this is my content'));
    }

    /**
     * @covers ::removeChild
     */
    public function testAvailableDeviceSizeIncreasesWhenChildIsRemoved() : void {
        $device = new Device('name');
        $device->setDeviceSize(1000);

        $device->addChild(new File('name1', 'this is my content'));
        $device->addChild(new File('name2', 'this is some other content'));

        $this->assertSame(956, $device->getAvailableSize(), 'Expected 956 bytes to be available on the device');
        $device->removeChild('name1');
        $this->assertSame(974, $device->getAvailableSize(), 'Expected 974 bytes to be available on the device');
        $device->removeChild('name2');
        $this->assertSame(1000, $device->getAvailableSize(), 'Expected 1000 bytes to be available on the device');
    }
}

class UnsupportedAsset extends Asset {
    public function getType() : int {
        return 0;
    }

    public function getSize() : int {
        return 0;
    }

    public function getDefaultMode() : int {
        return 0777;
    }
}