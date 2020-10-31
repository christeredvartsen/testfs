<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;
use TestFs\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass TestFs\Asset
 */
class AssetTest extends TestCase {
    /**
     * @covers ::__construct
     * @covers ::getLastAccessed
     * @covers ::getLastModified
     * @covers ::getLastMetadataModified
     */
    public function testConstructor() : void {
        $asset = new File('name');
        $this->assertTrue(0 < $atime = $asset->getLastAccessed(), 'Last accessed time not set');
        $this->assertTrue(0 < $mtime = $asset->getLastModified(), 'Last modified time not set');
        $this->assertTrue(0 < $ctime = $asset->getLastMetadataModified(), 'Last inode change time not set');
        $this->assertTrue(($atime === $mtime) && ($mtime === $ctime), 'Timestamps should be the same');
    }

    /**
     * @covers ::setUid
     * @covers ::getUid
     */
    public function testCanSetAndGetUid() : void {
        $asset = new Directory('name');
        $asset->setUid(123);
        $this->assertSame(123, $asset->getUid(), 'Incorrect UID');
    }

    /**
     * @covers ::setGid
     * @covers ::getGid
     */
    public function testCanSetAndGetGid() : void {
        $asset = new Directory('name');
        $asset->setGid(123);
        $this->assertSame(123, $asset->getGid(), 'Incorrect GID');
    }

    /**
     * @covers ::setParent
     * @covers ::getParent
     */
    public function testCanSetAndGetParent() : void {
        $asset = new File('name');
        $this->assertNull($asset->getParent(), 'Expected parent to be null');
        $parent = new Directory('name');
        $asset->setParent($parent);
        $this->assertSame($parent, $asset->getParent(), 'Incorrect parent instance');
    }

    /**
     * @covers ::setMode
     * @covers ::getMode
     */
    public function testCanSetAndGetMode() : void {
        $asset = new File('name');
        $asset->setMode(0600);
        $this->assertSame(0600, $asset->getMode(), 'Incorrect mode');
    }

    /**
     * @covers ::getType
     */
    public function testCanGetAssetType() : void {
        $this->assertSame(0100000, (new File('name'))->getType(), 'Incorrect default mode');
        $this->assertSame(0040000, (new Directory('name'))->getType(), 'Incorrect default mode');
    }

    /**
     * @covers ::setName
     * @covers ::getName
     */
    public function testCanSetAndGetName() : void {
        $asset = new File('name');
        $this->assertSame('name', $asset->getName(), 'Incorrect name');
        $asset->setName('newname');
        $this->assertSame('newname', $asset->getName(), 'Incorrect name');
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function getInvalidAssetNames() : array {
        return [
            'empty name' => [
                'name'             => ' ',
                'exceptionMessage' => 'Name can not be empty',
            ],
            'dir separators' => [
                'name'             => 'foo/bar',
                'exceptionMessage' => 'Name can not contain a directory separator',
            ],
        ];
    }

    /**
     * @dataProvider getInvalidAssetNames
     * @covers ::setName
     */
    public function testThrowsExceptionOnInvalidName(string $name, string $exceptionMessage) : void {
        $this->expectExceptionObject(new InvalidArgumentException($exceptionMessage));
        new File($name);
    }

    /**
     * @covers ::setParent
     */
    public function testThrowsExceptionWhenNameAlreadyExistsWhenSettingParent() : void {
        $parent = new Directory('parent');
        $parent->addChild(new File('name'));

        $this->expectExceptionObject(new InvalidArgumentException('Target directory already has a child named "name"'));
        (new File('name'))->setParent($parent);
    }

    /**
     * @covers ::setName
     */
    public function testThrowsExceptionWhenNameAlreadyExistsWhenChangingName() : void {
        $asset = new File('name');
        $parent = new Directory('parent');
        $parent->addChild(new File('othername'));
        $asset->setParent($parent);

        $this->expectExceptionObject(new InvalidArgumentException('There exists an asset with the same name in this directory'));
        $asset->setName('othername');
    }

    /**
     * @covers ::updateLastAccessed
     */
    public function testCanUpdateLastAccessed() : void {
        $time = time();
        $asset = new File('name');
        $asset->updateLastAccessed($time);
        $this->assertSame($time, $asset->getLastAccessed(), 'Last accessed timestamp does not match');
    }

    /**
     * @covers ::updateLastAccessed
     */
    public function testCanUpdateLastAccessedToCurrentTimestamp() : void {
        $asset = new File('name');
        $asset->updateLastAccessed();
        $this->assertTrue(0 < $asset->getLastAccessed(), 'Expected last accessed timestamp to have a value');
    }

    /**
     * @covers ::updateLastModified
     */
    public function testCanUpdateLastModified() : void {
        $time = time();
        $asset = new File('name');
        $asset->updateLastModified($time);
        $this->assertSame($time, $asset->getLastModified(), 'Last modified timestamp does not match');
    }

    /**
     * @covers ::updateLastModified
     */
    public function testCanUpdateLastModifiedToCurrentTimestamp() : void {
        $asset = new File('name');
        $asset->updateLastModified();
        $this->assertTrue(0 < $asset->getLastModified(), 'Expected last modified timestamp to have a value');
    }

    /**
     * @covers ::updateLastMetadataModified
     */
    public function testCanUpdateLastMetadataModified() : void {
        $time = time();
        $asset = new File('name');
        $asset->updateLastMetadataModified($time);
        $this->assertSame($time, $asset->getLastMetadataModified(), 'Last metadata modified timestamp does not match');
    }

    /**
     * @covers ::updateLastMetadataModified
     */
    public function testCanUpdateLastMetadataModifiedToCurrentTimestamp() : void {
        $asset = new File('name');
        $asset->updateLastMetadataModified();
        $this->assertTrue(0 < $asset->getLastMetadataModified(), 'Expected last metadata modified timestamp to have a value');
    }

    /**
     * @covers ::delete
     */
    public function testCanDeleteFromParent() : void {
        $directory = $this->createMock(Directory::class);
        $directory
            ->expects($this->once())
            ->method('removeChild')
            ->with('name');
        $file = new File('name');
        $file->setParent($directory);
        $file->delete();
    }

    /**
     * @covers ::delete
     */
    public function testThrowsExceptionWhenDeletingWithNoParent() : void {
        $this->expectExceptionObject(new RuntimeException('The asset does not have a parent'));
        (new File('name'))->delete();
    }

    /**
     * @covers ::detach
     */
    public function testCanDetachFromParent() : void {
        $dir = $this->createMock(Directory::class);
        $dir
            ->expects($this->once())
            ->method('removeChild');

        $file = new File('name');
        $file->setParent($dir);

        $file->detach();

        $this->assertNull($file->getParent(), 'Did not expect parent to exist');

        // Call again to make sure an error does not occur when detaching an already detached asset
        $file->detach();
    }

    /**
     * @covers ::setParent
     */
    public function testSettingParentRemovesAssetFromExistingParent() : void {
        $dir1 = new Directory('dir1');
        $dir2 = new Directory('dir2');
        $file = new File('file');

        $file->setParent($dir1);
        $this->assertTrue($dir1->hasChild('file'), 'Expected directory to have file');
        $file->setParent($dir2);
        $this->assertFalse($dir1->hasChild('file'), 'Did not expect directory to have child');
        $this->assertTrue($dir2->hasChild('file'), 'Expected directory to have file');
    }

    /**
     * @covers ::setParent
     */
    public function testSetExistingParentReturnsEarly() : void {
        $directory = $this->createMock(Directory::class);
        $directory
            ->expects($this->once())
            ->method('hasChild');

        $file = new File('name');
        $file->setParent($directory);
        $file->setParent($directory);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function getAccessCheckDataForReadable() : array {
        return [
            'root user' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0444,
                'checkUid'       => 0,
                'checkGid'       => 0,
                'expectedResult' => true,
            ],
            'root group' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0444,
                'checkUid'       => 2,
                'checkGid'       => 0,
                'expectedResult' => true,
            ],
            'user has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0444,
                'checkUid'       => 1,
                'checkGid'       => 2,
                'expectedResult' => true,
            ],
            'group has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0444,
                'checkUid'       => 2,
                'checkGid'       => 1,
                'expectedResult' => true,
            ],
            'everyone has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0444,
                'checkUid'       => 2,
                'checkGid'       => 2,
                'expectedResult' => true,
            ],
            'no access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0440,
                'checkUid'       => 2,
                'checkGid'       => 2,
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @dataProvider getAccessCheckDataForReadable
     * @covers ::isReadable
     */
    public function testCanCheckIfAssetIsReadable(int $ownerUid, int $ownerGid, int $mode, int $checkUid, int $checkGid, bool $expectedResult) : void {
        $file = new File('filename');
        $file->setUid($ownerUid);
        $file->setGid($ownerGid);
        $file->setMode($mode);

        $this->assertSame($expectedResult, $file->isReadable($checkUid, $checkGid), 'Wrong result for isReadable');
    }

    /**
     * @covers ::isReadable
     */
    public function testAssetIsNotReadableWhenParentIsNotReadable() : void {
        $dir = new Directory('dir');
        $dir->setUid(1);
        $dir->setGid(1);
        $dir->setMode(0770);

        $file = new File('file');
        $file->setUid(2);
        $file->setGid(2);
        $file->setMode(0777);
        $file->setParent($dir);

        $this->assertTrue($file->isReadable(1, 1), 'Expected user to be able to read file');
        $this->assertFalse($file->isReadable(2, 2), 'Did not expect user to be able to read');
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function getAccessCheckDataForWritable() : array {
        return [
            'root user' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0666,
                'checkUid'       => 0,
                'checkGid'       => 0,
                'expectedResult' => true,
            ],
            'root group' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0666,
                'checkUid'       => 2,
                'checkGid'       => 0,
                'expectedResult' => true,
            ],
            'user has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0666,
                'checkUid'       => 1,
                'checkGid'       => 2,
                'expectedResult' => true,
            ],
            'group has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0666,
                'checkUid'       => 2,
                'checkGid'       => 1,
                'expectedResult' => true,
            ],
            'everyone has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0666,
                'checkUid'       => 2,
                'checkGid'       => 2,
                'expectedResult' => true,
            ],
            'no access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0660,
                'checkUid'       => 2,
                'checkGid'       => 2,
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @dataProvider getAccessCheckDataForWritable
     * @covers ::isWritable
     */
    public function testCanCheckIfAssetIsWritable(int $ownerUid, int $ownerGid, int $mode, int $checkUid, int $checkGid, bool $expectedResult) : void {
        $file = new File('filename');
        $file->setUid($ownerUid);
        $file->setGid($ownerGid);
        $file->setMode($mode);

        $this->assertSame($expectedResult, $file->isWritable($checkUid, $checkGid), 'Wrong result for isWritable');
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function getAccessCheckDataForExecutable() : array {
        return [
            'root user' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0555,
                'checkUid'       => 0,
                'checkGid'       => 0,
                'expectedResult' => true,
            ],
            'root group' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0555,
                'checkUid'       => 2,
                'checkGid'       => 0,
                'expectedResult' => true,
            ],
            'user has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0555,
                'checkUid'       => 1,
                'checkGid'       => 2,
                'expectedResult' => true,
            ],
            'group has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0555,
                'checkUid'       => 2,
                'checkGid'       => 1,
                'expectedResult' => true,
            ],
            'everyone has access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0555,
                'checkUid'       => 2,
                'checkGid'       => 2,
                'expectedResult' => true,
            ],
            'no access' => [
                'ownerUid'       => 1,
                'ownerGid'       => 1,
                'mode'           => 0550,
                'checkUid'       => 2,
                'checkGid'       => 2,
                'expectedResult' => false,
            ],
        ];
    }

    /**
     * @dataProvider getAccessCheckDataForExecutable
     * @covers ::isExecutable
     */
    public function testCanCheckIfAssetIsExecutable(int $ownerUid, int $ownerGid, int $mode, int $checkUid, int $checkGid, bool $expectedResult) : void {
        $file = new File('filename');
        $file->setUid($ownerUid);
        $file->setGid($ownerGid);
        $file->setMode($mode);

        $this->assertSame($expectedResult, $file->isExecutable($checkUid, $checkGid), 'Wrong result for isExecutable');
    }

    /**
     * @covers ::isOwnedByUser
     */
    public function testCanCheckIfAssetIsOwnedByUser() : void {
        $asset = new File('filename');
        $this->assertTrue($asset->isOwnedByUser(0), 'Expected UID 0 to own the asset');
        $asset->setUid(1);
        $this->assertFalse($asset->isOwnedByUser(0), 'Did not expect UID 0 to own the asset');
        $this->assertTrue($asset->isOwnedByUser(1), 'Expected UID 1 to own the asset');
    }

    /**
     * @covers ::getDevice
     * @covers TestFs\Device::getDevice
     */
    public function testCanGetDevice() : void {
        $file   = new File('name');
        $dir    = new Directory('name');
        $device = new Device('some name');
        $dir->addChild($file);
        $device->addChild($dir);

        $this->assertSame($device, $file->getDevice(), 'Incorrect instance returned');
        $this->assertSame($device, $dir->getDevice(), 'Incorrect instance returned');
        $this->assertSame($device, $device->getDevice(), 'Incorrect instance returned');
    }

    /**
     * @covers ::getDevice
     */
    public function testReturnNullIfThereIsNoDevice() : void {
        $file = new File('name');
        $dir  = new Directory('name');
        $dir->addChild($file);

        $this->assertNull($file->getDevice(), 'Did not expect any device');
        $this->assertNull($dir->getDevice(), 'Did not expect any device');
    }
}