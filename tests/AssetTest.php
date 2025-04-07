<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TestFs\Exception\DuplicateAssetException;
use TestFs\Exception\InvalidAssetNameException;

#[CoversClass(Asset::class)]
class AssetTest extends TestCase
{
    public function testConstructor(): void
    {
        $asset = new File('name');
        $this->assertTrue(0 < $atime = $asset->getLastAccessed(), 'Last accessed time not set');
        $this->assertTrue(0 < $mtime = $asset->getLastModified(), 'Last modified time not set');
        $this->assertTrue(0 < $ctime = $asset->getLastMetadataModified(), 'Last inode change time not set');
        $this->assertTrue(($atime === $mtime) && ($mtime === $ctime), 'Timestamps should be the same');
    }

    public function testCanSetAndGetUid(): void
    {
        $asset = new Directory('name');
        $asset->setUid(123);
        $this->assertSame(123, $asset->getUid(), 'Incorrect UID');
    }

    public function testCanSetAndGetGid(): void
    {
        $asset = new Directory('name');
        $asset->setGid(123);
        $this->assertSame(123, $asset->getGid(), 'Incorrect GID');
    }

    public function testCanGetParent(): void
    {
        $asset = new File('name');
        $this->assertNull($asset->getParent(), 'Expected parent to be null');
        $parent = new Directory('name');
        $parent->addChild($asset);
        $this->assertSame($parent, $asset->getParent(), 'Incorrect parent instance');
    }

    public function testCanSetAndGetMode(): void
    {
        $asset = new File('name');
        $asset->setMode(0600);
        $this->assertSame(0600, $asset->getMode(), 'Incorrect mode');
    }

    public function testCanGetAssetType(): void
    {
        $this->assertSame(Asset::TYPE_FILE, (new File('name'))->getType(), 'Incorrect default mode');
        $this->assertSame(Asset::TYPE_DIRECTORY, (new Directory('name'))->getType(), 'Incorrect default mode');
    }

    public function testCanSetAndGetName(): void
    {
        $asset = new File('name');
        $this->assertSame('name', $asset->getName(), 'Incorrect name');
        $asset->setName('newname');
        $this->assertSame('newname', $asset->getName(), 'Incorrect name');
    }

    #[DataProvider('getInvalidAssetNames')]
    public function testThrowsExceptionOnInvalidName(string $name, string $exceptionMessage): void
    {
        $this->expectExceptionObject(new InvalidAssetNameException($exceptionMessage));
        new File($name);
    }

    public function testThrowsExceptionWhenNameAlreadyExistsWhenChangingName(): void
    {
        $asset = new File('name');
        $parent = new Directory('parent');
        $parent->addChild($asset);
        $parent->addChild(new File('othername'));

        $this->expectException(DuplicateAssetException::class);
        $this->expectExceptionMessage('Directory "parent" already has a child named "othername"');
        $asset->setName('othername');
    }

    public function testCanUpdateLastAccessed(): void
    {
        $time = time();
        $asset = new File('name');
        $asset->updateLastAccessed($time);
        $this->assertSame($time, $asset->getLastAccessed(), 'Last accessed timestamp does not match');
    }

    public function testCanUpdateLastAccessedToCurrentTimestamp(): void
    {
        $asset = new File('name');
        $asset->updateLastAccessed();
        $this->assertTrue(0 < $asset->getLastAccessed(), 'Expected last accessed timestamp to have a value');
    }

    public function testCanUpdateLastModified(): void
    {
        $time = time();
        $asset = new File('name');
        $asset->updateLastModified($time);
        $this->assertSame($time, $asset->getLastModified(), 'Last modified timestamp does not match');
    }

    public function testCanUpdateLastModifiedToCurrentTimestamp(): void
    {
        $asset = new File('name');
        $asset->updateLastModified();
        $this->assertTrue(0 < $asset->getLastModified(), 'Expected last modified timestamp to have a value');
    }

    public function testCanUpdateLastMetadataModified(): void
    {
        $time = time();
        $asset = new File('name');
        $asset->updateLastMetadataModified($time);
        $this->assertSame($time, $asset->getLastMetadataModified(), 'Last metadata modified timestamp does not match');
    }

    public function testCanUpdateLastMetadataModifiedToCurrentTimestamp(): void
    {
        $asset = new File('name');
        $asset->updateLastMetadataModified();
        $this->assertTrue(0 < $asset->getLastMetadataModified(), 'Expected last metadata modified timestamp to have a value');
    }

    public function testCanDetachFromParent(): void
    {
        $file = new File('name');
        $dir = new Directory('parent');
        $dir->addChild($file);

        $file->detach();

        $this->assertNull($file->getParent(), 'Did not expect parent to exist');
        $this->assertFalse($dir->hasChild('name'), 'Did not expect parent to have child');

        // Call again to make sure an error does not occur when detaching an already detached asset
        $file->detach();
    }

    #[DataProvider('getAccessCheckDataForReadable')]
    public function testCanCheckIfAssetIsReadable(int $ownerUid, int $ownerGid, int $mode, int $checkUid, int $checkGid, bool $expectedResult): void
    {
        $file = new File('filename');
        $file->setUid($ownerUid);
        $file->setGid($ownerGid);
        $file->setMode($mode);

        $this->assertSame($expectedResult, $file->isReadable($checkUid, $checkGid), 'Wrong result for isReadable');
    }

    public function testAssetIsNotReadableWhenParentIsNotReadable(): void
    {
        $dir = new Directory('dir');
        $dir->setUid(1);
        $dir->setGid(1);
        $dir->setMode(0770);

        $file = new File('file');
        $file->setUid(2);
        $file->setGid(2);
        $file->setMode(0777);

        $dir->addChild($file);

        $this->assertTrue($file->isReadable(1, 1), 'Expected user to be able to read file');
        $this->assertFalse($file->isReadable(2, 2), 'Did not expect user to be able to read');
    }

    #[DataProvider('getAccessCheckDataForWritable')]
    public function testCanCheckIfAssetIsWritable(int $ownerUid, int $ownerGid, int $mode, int $checkUid, int $checkGid, bool $expectedResult): void
    {
        $file = new File('filename');
        $file->setUid($ownerUid);
        $file->setGid($ownerGid);
        $file->setMode($mode);

        $this->assertSame($expectedResult, $file->isWritable($checkUid, $checkGid), 'Wrong result for isWritable');
    }

    #[DataProvider('getAccessCheckDataForExecutable')]
    public function testCanCheckIfAssetIsExecutable(int $ownerUid, int $ownerGid, int $mode, int $checkUid, int $checkGid, bool $expectedResult): void
    {
        $file = new File('filename');
        $file->setUid($ownerUid);
        $file->setGid($ownerGid);
        $file->setMode($mode);

        $this->assertSame($expectedResult, $file->isExecutable($checkUid, $checkGid), 'Wrong result for isExecutable');
    }

    public function testCanCheckIfAssetIsOwnedByUser(): void
    {
        $asset = new File('filename');
        $this->assertTrue($asset->isOwnedByUser(0), 'Expected UID 0 to own the asset');
        $asset->setUid(1);
        $this->assertFalse($asset->isOwnedByUser(0), 'Did not expect UID 0 to own the asset');
        $this->assertTrue($asset->isOwnedByUser(1), 'Expected UID 1 to own the asset');
    }

    public function testCanGetDevice(): void
    {
        $file   = new File('name');
        $dir    = new Directory('name');
        $device = new Device();
        $dir->addChild($file);
        $device->getRoot()->addChild($dir);

        $this->assertSame($device, $file->getDevice(), 'Incorrect instance returned');
        $this->assertSame($device, $dir->getDevice(), 'Incorrect instance returned');
    }

    public function testReturnNullIfThereIsNoDevice(): void
    {
        $file = new File('name');
        $dir  = new Directory('name');
        $dir->addChild($file);

        $this->assertNull($file->getDevice(), 'Did not expect any device');
        $this->assertNull($dir->getDevice(), 'Did not expect any device');
    }

    /**
     * @return array<string,array{name:string,exceptionMessage:string}>
     */
    public static function getInvalidAssetNames(): array
    {
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
     * @return array<string,array{ownerUid:int,ownerGid:int,mode:int,checkUid:int,checkGid:int,expectedResult:bool}>
     */
    public static function getAccessCheckDataForReadable(): array
    {
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
     * @return array<string,array{ownerUid:int,ownerGid:int,mode:int,checkUid:int,checkGid:int,expectedResult:bool}>
     */
    public static function getAccessCheckDataForWritable(): array
    {
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
     * @return array<string,array{ownerUid:int,ownerGid:int,mode:int,checkUid:int,checkGid:int,expectedResult:bool}>
     */
    public static function getAccessCheckDataForExecutable(): array
    {
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
}
