<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass TestFs\File
 */
class FileTest extends TestCase {
    use ErrorHandler;

    /**
     * @covers ::getType
     */
    public function testCanGetFileType() : void {
        $this->assertSame(0100000, (new File('name'))->getType(), 'Incorrect file type');
    }

    /**
     * @dataProvider getFileContent
     * @covers ::getSize
     */
    public function testCanCalculateSize(string $content, int $expectedSize) : void {
        $this->assertSame($expectedSize, (new File('name', $content))->getSize(), 'Incorrect size');
    }

    /**
     * @dataProvider getContentForReading
     * @covers ::read
     * @covers ::getOffset
     */
    public function testCanRead(string $content, int $bytes, string $expectedOutput, int $expectedOffset) : void {
        $file = new File('name', $content);
        $this->assertSame($expectedOutput, $file->read($bytes), 'Incorrect output');
        $this->assertSame($expectedOffset, $file->getOffset(), 'Incorrect offset after reading');
    }

    /**
     * @covers ::eof
     */
    public function testCanCheckForEof() : void {
        $file = new File('name', 'contents');
        $this->assertFalse($file->eof(), 'Did not expect EOF');
        $this->assertSame('contents', $file->read(8));
        $this->assertTrue($file->eof(), 'Expected EOF');
    }

    /**
     * @dataProvider getContentForWriting
     * @covers ::write
     * @covers ::getContent
     * @covers ::__construct
     */
    public function testCanWriteData(string $existingContent, int $newDataLength, string $newData, string $newContent) : void {
        $file = new File('name', $existingContent);

        $this->assertSame($newDataLength, $file->write($newData), 'Incorrect length');
        $this->assertSame($newDataLength, $file->getOffset(), 'Incorrect offset after writing');
        $this->assertSame($newContent, $file->getContent(), 'Incorrect content after writing');
    }

    /**
     * @dataProvider getDataForTruncate
     * @covers ::truncate
     */
    public function testCanTruncateFile(string $existingContent, int $size, string $expectedContent) : void {
        $file = new File('name', $existingContent);
        $file->truncate($size);
        $this->assertSame($expectedContent, $file->getContent(), 'Incorrect content');
        $this->assertSame(0, $file->getOffset(), 'Offset is not supposed to be changed');
    }

    /**
     * @dataProvider getDataForSeek
     * @covers ::seek
     */
    public function testCanSeekInFile(string $content, int $seek, int $whence, int $expectedOffset, string $expectedContent) : void {
        $file = new File('name', $content);
        $file->seek($seek, $whence);
        $this->assertSame($expectedOffset, $file->getOffset(), 'Incorrect offset after seek');
        $this->assertSame($expectedContent, $file->getContent(), 'Incorrect content after seek');
    }

    /**
     * @covers ::seek
     * @covers ::forward
     * @covers ::rewind
     */
    public function testCanRewindAndForward() : void {
        $file = new File('name', 'some content');
        $file->forward();
        $this->assertSame(12, $file->getOffset(), 'Incorrect offset after seek');
        $file->rewind();
        $this->assertSame(0, $file->getOffset(), 'Incorrect offset after rewinding');
    }

    /**
     * @covers ::getDefaultMode
     */
    public function testGetDefaultMode() : void {
        $this->assertSame(0644, (new File('name'))->getMode(), 'Incorrect default mode');
    }

    /**
     * @covers ::isLocked
     * @covers ::hasSharedLock
     */
    public function testFileIsInitiallyUnlocked() : void {
        $file = new File('name');
        $this->assertFalse($file->isLocked(), 'Did not expect file to be locked');
        $this->assertFalse($file->hasSharedLock(), 'Did not expect file to have a shared lock');
    }

    /**
     * @covers ::lock
     * @covers ::isLocked
     * @covers ::hasSharedLock
     * @covers ::hasExclusiveLock
     */
    public function testCanHaveSharedLockOnSpecificId() : void {
        $file = new File('name');
        $this->assertTrue($file->lock('id', LOCK_SH), 'Expected lock to succeed');
        $this->assertTrue($file->isLocked(), 'Expected file to be locked');
        $this->assertFalse($file->hasExclusiveLock(), 'Did not expect file to have an exclusive lock');
        $this->assertTrue($file->hasSharedLock('id'), 'Expected id to have a shared lock');
        $this->assertTrue($file->hasSharedLock(), 'Expected file to have a shared lock');
        $this->assertFalse($file->hasSharedLock('other-id'), 'Did not expect other id to have shared lock');
    }

    /**
     * @covers ::lock
     * @covers ::unlock
     * @covers ::hasExclusiveLock
     * @covers ::hasSharedLock
     */
    public function testCanAcquireLocksMultipleTimes() : void {
        $id = 'id';
        $file = new File('name');
        $this->assertTrue($file->lock($id, LOCK_EX), 'Expected locking to succeed');
        $this->assertTrue($file->hasExclusiveLock($id), 'Expected id to have exclusive lock');
        $this->assertFalse($file->hasSharedLock($id), 'Did not expect id to have shared lock');

        $this->assertTrue($file->lock($id, LOCK_SH), 'Expected lock to succeed');
        $this->assertFalse($file->hasExclusiveLock($id), 'Did not expect id to have exclusive lock');
        $this->assertTrue($file->hasSharedLock($id), 'Expected id to have shared lock');
    }

    /**
     * @covers ::lock
     */
    public function testWillNotGetExclusiveLockOnLockedFile() : void {
        $file = new File('name');
        $this->assertTrue($file->lock('id', LOCK_EX), 'Expected lock to succeed');
        $this->assertFalse($file->lock('other-id', LOCK_EX), 'Expected lock to fail');
        $this->assertFalse($file->lock('other-id', LOCK_SH), 'Expected lock to fail');
    }

    /**
     * @covers ::lock
     * @covers ::unlock
     */
    public function testCanUnlock() : void {
        $file = new File('name');
        $this->assertTrue($file->lock('id', LOCK_EX), 'Expected lock to succeed');
        $this->assertTrue($file->hasExclusiveLock('id'), 'Expected id to have exclusive lock');
        $this->assertTrue($file->lock('id', LOCK_UN), 'Expected unlocking to succeed');
        $this->assertFalse($file->hasExclusiveLock('id'), 'Did not expect id to have exclusive lock');
    }

    /**
     * @covers ::write
     */
    public function testTruncateDoesNotResetPointer() : void {
        $file = new File('name', 'this is some text');
        $file->seek(5);
        $file->truncate(2);
        $file->write('new text');

        $this->assertSame("th\0\0\0new text", $file->getContent());
    }

    /**
     * @covers ::read
     * @covers ::seek
     * @covers ::setAppendMode
     * @covers ::getAppendMode
     */
    public function testReadInAppendModeAlwaysReturnsEmptyString() : void {
        $file = new File('name', 'content');
        $this->assertFalse($file->getAppendMode(), 'Expected append mode to be false');
        $file->setAppendMode(true);
        $this->assertTrue($file->getAppendMode(), 'Expected append mode to be true');
        $this->assertFalse($file->seek(0), 'Did not expect seek to work');
        $this->assertSame('', $file->read(7), 'Expected empty string');
    }

    /**
     * @covers ::setAppendMode
     * @covers ::write
     * @covers ::seek
     */
    public function testWriteInAppendModeAlwaysAppends() : void {
        $file = new File('name', 'content');
        $file->setAppendMode(true);
        $this->assertFalse($file->seek(0), 'Did not expect seek to work');
        $file->write('some data');
        $this->assertSame('contentsome data', $file->getContent(), 'Incorrect data after write');
    }

    /**
     * @covers ::lock
     */
    public function testLockingFailsOnUnsupportedLock() : void {
        $this->assertFalse((new File('name', 'content'))->lock('some-id', LOCK_EX | LOCK_NB), 'Expected locking to fail');
    }

    /**
     * @covers ::setRead
     * @covers ::read
     */
    public function testReadReturnsEmptyStringWhenReadModeIsDisabled() : void {
        $file = new File('name', 'some content');
        $file->setRead(false);
        $this->assertSame('', $file->read(4), 'Expected read to return empty string');
    }

    /**
     * @covers ::setWrite
     * @covers ::write
     */
    public function testWriteReturnsZeroWhenWriteModeIsDisabled() : void {
        $file = new File('name', 'content');
        $file->setWrite(false);
        $this->assertSame(0, $file->write('more content'), 'Expected write to return 0');
    }

    /**
     * @covers ::setWrite
     * @covers ::truncate
     */
    public function testTruncateFailsWhenWriteModeIsDisabled() : void {
        $file = new File('name', 'content');
        $file->setWrite(false);
        $this->assertFalse($file->truncate(2), 'Expected truncate to return false');
    }

    /**
     * @covers ::write
     */
    public function testTruncateDataWhenThereIsNotEnoughSpaceOnDevice() : void {
        $file   = new File('name');
        $device = new Device('some name');
        $device->setDeviceSize(7);
        $device->addChild($file);

        $this->ignoreError(fn () => $file->write('some data'));
        $this->assertSame('some da', $file->getContent());

        $this->expectExceptionObject(new Notice('fwrite(): write failed, no space left on device'));
        $file->write('some data');
    }

    /**
     * @return array<string,array{content:string,expectedSize:int}>
     */
    public static function getFileContent() : array {
        return [
            'content' => [
                'content' => 'some content',
                'expectedSize' => 12,
            ],
            'no content' => [
                'content' => '',
                'expectedSize' => 0,
            ],
        ];
    }

    /**
     * @return array<string,array{content:string,bytes:int,expectedOutput:string,expectedOffset:int}>
     */
    public static function getContentForReading() : array {
        return [
            'empty file' => [
                'content'        => '',
                'bytes'          => 10,
                'expectedOutput' => '',
                'expectedOffset' => 0
            ],
            'file with contents' => [
                'content'        => 'this is some data',
                'bytes'          => 7,
                'expectedOutput' => 'this is',
                'expectedOffset' => 7
            ]
        ];
    }

    /**
     * @return array<string,array{existingContent:string,newDataLength:int,newData:string,newContent:string}>
     */
    public static function getContentForWriting() : array {
        return [
            'empty string' => [
                'existingContent' => 'existing content',
                'newDataLength'   => 0,
                'newData'         => '',
                'newContent'      => 'existing content',
            ],
            'write some data' => [
                'existingContent' => 'exsiting content',
                'newDataLength'   => 9,
                'newData'         => 'some data',
                'newContent'      => 'some datacontent',
            ],
        ];
    }

    /**
     * @return array<string,array{existingContent:string,size:int,expectedContent:string}>
     */
    public static function getDataForTruncate() : array {
        return [
            'truncate empty file' => [
                'existingContent' => '',
                'size'            => 0,
                'expectedContent' => '',
            ],
            'truncate file with contents' => [
                'existingContent' => 'existing content',
                'size'            => 0,
                'expectedContent' => '',
            ],
            'truncate to larger size' => [
                'existingContent' => 'content',
                'size'            => 10,
                'expectedContent' => "content\0\0\0",
            ],
            'truncate to smaller size and rewind' => [
                'existingContent' => 'content',
                'size'            => 4,
                'expectedContent' => 'cont',
            ],
        ];
    }

    /**
     * @return array<string,array{content:string,seek:int,whence:int,expectedOffset:int,expectedContent:string}>
     */
    public static function getDataForSeek() : array {
        return [
            'set' => [
                'content'         => 'file content',
                'seek'            => 5,
                'whence'          => SEEK_SET,
                'expectedOffset'  => 5,
                'expectedContent' => 'file content',
            ],
            'cur' => [
                'content'         => 'file content',
                'seek'            => 3,
                'whence'          => SEEK_CUR,
                'expectedOffset'  => 3,
                'expectedContent' => 'file content',
            ],
            'end' => [
                'content'         => 'file content',
                'seek'            => 3,
                'whence'          => SEEK_END,
                'expectedOffset'  => 15,
                'expectedContent' => "file content\0\0\0",
            ],
        ];
    }
}
