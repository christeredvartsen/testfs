<?php declare(strict_types=1);
namespace TestFs;

use TestFs\Exception\InvalidArgumentException;
use TestFs\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass TestFs\StreamWrapper
 */
class StreamWrapperTest extends TestCase {
    use ErrorHandler;

    private Device $device;

    public function setUp() : void {
        if (!StreamWrapper::register()) {
            $this->fail('Unable to register streamwrapper');
        }

        $device = StreamWrapper::getDevice();

        if (null === $device) {
            $this->fail('Wrapper has not been properly initialized');
        }

        $this->device = $device;
    }

    public function tearDown() : void {
        StreamWrapper::unregister();
    }

    /**
     * @covers ::register
     */
    public function testCanNotRegisterTwice() : void {
        $this->expectExceptionObject(new RuntimeException('Protocol "tfs" is already registered'));
        StreamWrapper::register();
    }

    /**
     * @covers ::register
     * @covers ::unregister
     */
    public function testCanForceRegister() : void {
        $this->assertTrue(StreamWrapper::register(true), 'Expected registration to succeed');
    }

    /**
     * @covers ::mkdir
     * @covers ::getDevice
     * @covers ::register
     * @covers ::unregister
     * @covers ::getAssetFactory
     */
    public function testCanCreateDirectory() : void {
        $this->assertSame($this->device, StreamWrapper::getDevice());
        $this->assertTrue(mkdir('tfs://foobar'));
        $this->assertTrue($this->device->hasChild('foobar'));
        $this->assertInstanceOf(Directory::class, $this->device->getChild('foobar'));
    }

    /**
     * @covers ::mkdir
     */
    public function testCanCreateDirectoryRecursively() : void {
        $this->assertTrue(mkdir('tfs://foo/bar/baz', 0777, true));
        $child = $this->device->getChildDirectory('foo');
        $this->assertInstanceOf(Directory::class, $child);

        $child = $child->getChildDirectory('bar');
        $this->assertInstanceOf(Directory::class, $child);

        $this->assertTrue($child->hasChild('baz'));
        $this->assertInstanceOf(Directory::class, $child->getChild('baz'));
    }

    /**
     * @covers ::mkdir
     */
    public function testMkdirFailsWhenNameExists() : void {
        $this->assertTrue(mkdir('tfs://foobar'));
        $this->assertFalse($this->ignoreError(fn () => mkdir('tfs://foobar')));
        $this->expectExceptionObject(new Warning('mkdir(): File exists'));
        mkdir('tfs://foobar');
    }

    /**
     * @covers ::mkdir
     */
    public function testMkdirFailsOnNonRecursiveWhenADirIsMissing() : void {
        $this->assertFalse($this->ignoreError(fn () => mkdir('tfs://foo/bar')));
        $this->expectExceptionObject(new Warning('mkdir(): No such file or directory'));
        mkdir('tfs://foo/bar');
    }

    /**
     * @covers ::mkdir
     */
    public function testCanCreateDirsWhenSomeDirsExist() : void {
        $this->assertTrue(mkdir('tfs://foo'));
        $this->assertTrue(mkdir('tfs://foo/bar'));

        $dir = $this->device->getChild('foo');
        $this->assertInstanceOf(Directory::class, $dir);
        $this->assertTrue($dir->hasChild('bar'));
        $this->assertInstanceOf(Directory::class, $dir->getChild('bar'));
    }

    /**
     * @covers ::rmdir
     */
    public function testCanRemoveDir() : void {
        $this->assertTrue(mkdir('tfs://foo'));
        $this->assertTrue($this->device->hasChild('foo'));
        $this->assertTrue(rmdir('tfs://foo'));
        $this->assertFalse($this->device->hasChild('foo'));
    }

    /**
     * @covers ::rmdir
     */
    public function testRmDirFailsWhenDeletingANonExistingDir() : void {
        $this->assertFalse($this->ignoreError(fn () => rmdir('tfs://foo')));
        $this->expectExceptionObject(new Warning('rmdir(foo): No such file or directory'));
        rmdir('tfs://foo');
    }

    /**
     * @covers ::rmdir
     */
    public function testRmDirFailsWhenDeletingANonDirectory() : void {
        $this->assertTrue(touch('tfs://foo'));
        $this->assertFalse($this->ignoreError(fn () => rmdir('tfs://foo')));
        $this->expectExceptionObject(new Warning('rmdir(foo): Not a directory'));
        rmdir('tfs://foo');
    }

    /**
     * @covers ::rmdir
     */
    public function testRmDirFailsWhenDeletingANonEmptyDirectory() : void {
        $this->assertTrue(mkdir('tfs://foo'));
        $this->assertTrue(touch('tfs://foo/bar'));
        $this->assertFalse($this->ignoreError(fn () => rmdir('tfs://foo')));
        $this->expectExceptionObject(new Warning('rmdir(foo): Not empty'));
        rmdir('tfs://foo');
    }

    /**
     * @covers ::rmdir
     */
    public function testRmDirFailsOnMissingPermissions() : void {
        mkdir('tfs://root', 0770);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => rmdir('tfs://root')));

        $this->expectExceptionObject(new Warning('rmdir(root): Permission denied'));
        rmdir('tfs://root');
    }

    /**
     * @covers ::dir_opendir
     * @covers ::dir_readdir
     * @covers ::dir_rewinddir
     * @covers ::dir_closedir
     * @covers ::url_stat
     * @covers ::assetStat
     */
    public function testCanOpenAndReadDirectories() : void {
        $this->assertTrue(mkdir('tfs://foo/bar', 0777, true));

        $entries = [
            'tfs://foo/bar/baz.txt' => ['type' => 'dir', 'expectedName' => 'bar'],
            'tfs://foo/bar.txt' => ['type' => 'file', 'expectedName' => 'bar.txt'],
            'tfs://foo/baz.txt' => ['type' => 'file', 'expectedName' => 'baz.txt'],
        ];

        foreach (array_keys($entries) as $name) {
            touch(sprintf($name));
        }

        $path = 'tfs://foo';
        $handle = opendir($path);

        foreach ($entries as $entry) {
            $asset = readdir($handle);
            $this->assertSame($entry['expectedName'], $asset);
            $this->assertSame($entry['type'], filetype(sprintf('%s/%s', $path, $asset)));
        }

        rewinddir($handle);

        foreach ($entries as $entry) {
            $asset = readdir($handle);
            $this->assertSame($entry['expectedName'], $asset);
            $this->assertSame($entry['type'], filetype(sprintf('%s/%s', $path, $asset)));
        }

        $this->assertFalse(readdir($handle), 'Should not get more entries');

        closedir($handle);
    }

    /**
     * @covers ::dir_opendir
     */
    public function testFailsWhenOpeningDirectoryThatDoesNotExist() : void {
        $this->assertFalse($this->ignoreError(fn () => opendir('tfs://foo')));
        $this->expectExceptionObject(new Warning('opendir(tfs://foo): failed to open dir: No such file or directory'));
        opendir('tfs://foo');
    }

    /**
     * @covers ::dir_opendir
     */
    public function testFailsWhenOpeningFileAsDir() : void {
        touch('tfs://foo');
        $this->assertFalse($this->ignoreError(fn () => opendir('tfs://foo')));
        $this->expectExceptionObject(new Warning('opendir(tfs://foo): failed to open dir: Not a directory'));
        opendir('tfs://foo');
    }

    /**
     * @dataProvider getUrls
     * @covers ::urlToPath
     */
    public function testCanConvertUrlToPath(string $url, string $expectedPath) : void {
        $this->assertSame($expectedPath, (new StreamWrapper())->urlToPath($url));
    }

    /**
     * @covers ::urlToPath
     */
    public function testUrlToPathFailsOnInvalidUrl() : void {
        $this->expectExceptionObject(new InvalidArgumentException('Invalid URL: foo://bar'));
        (new StreamWrapper())->urlToPath('foo://bar');
    }

    /**
     * @dataProvider getPaths
     * @covers ::url
     */
    public function testCanConvertPathToUrl(string $path, string $expectedUrl) : void {
        $this->assertSame($expectedUrl, (new StreamWrapper())->url($path));
    }

    /**
     * @covers ::unlink
     */
    public function testCanRemoveFile() : void {
        touch('tfs://foo.bar');
        $this->assertTrue(unlink('tfs://foo.bar'));
    }

    /**
     * @covers ::unlink
     */
    public function testRemoveFileThatDoesNotExistFails() : void {
        $this->assertFalse($this->ignoreError(fn () => unlink('tfs://foo.bar')));
        $this->expectExceptionObject(new Warning('unlink(foo.bar): No such file or directory'));
        unlink('tfs://foo.bar');
    }

    /**
     * @covers ::unlink
     */
    public function testUnlinkDirectoryFails() : void {
        mkdir('tfs://foo');
        $this->assertFalse($this->ignoreError(fn () => unlink('tfs://foo')));

        $this->expectExceptionObject(new Warning('unlink(foo): Is a directory'));
        unlink('tfs://foo');
    }

    /**
     * @covers ::unlink
     */
    public function testUnlinkFailsWhenDirIsNotWritable() : void {
        mkdir('tfs://dir', 0770);
        touch('tfs://dir/file');

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => unlink('tfs://dir/file')));

        $this->expectExceptionObject(new Warning('unlink(dir/file): Permission denied'));
        unlink('tfs://dir/file');
    }

    /**
     * @covers ::stream_open
     * @covers ::stream_write
     * @covers ::stream_read
     * @covers ::stream_close
     * @covers ::stream_stat
     * @covers ::parseFopenMode
     */
    public function testCanWriteAndReadCompleteFiles() : void {
        $handle = $this->getHandleForFixture('tfs://foo.txt', 'r', FIXTURES_DIR . '/file.txt');
        $this->assertTrue(fclose($handle));
        $this->assertSame(file_get_contents(FIXTURES_DIR . '/file.txt'), file_get_contents('tfs://foo.txt'));
    }

    /**
     * @covers ::setUid
     */
    public function testThrowsExceptionWhenSettingUidThatDoesNotExist() : void {
        $this->expectExceptionObject(new InvalidArgumentException('UID 42 does not exist'));
        StreamWrapper::setUid(42);
    }

    /**
     * @covers ::setGid
     */
    public function testThrowsExceptionWhenSettingGidThatDoesNotExist() : void {
        $this->expectExceptionObject(new InvalidArgumentException('GID 42 does not exist'));
        StreamWrapper::setGid(42);
    }

    /**
     * @covers ::getUid
     * @covers ::setUid
     */
    public function testCanSetAndGetUid() : void {
        $this->assertSame(0, StreamWrapper::getUid());
        StreamWrapper::addUser(42, 'user');
        StreamWrapper::setUid(42);
        $this->assertSame(42, StreamWrapper::getUid());
    }

    /**
     * @covers ::getGid
     * @covers ::setGid
     */
    public function testCanGetGid() : void {
        $this->assertSame(0, StreamWrapper::getGid());
        StreamWrapper::addGroup(42, 'group');
        StreamWrapper::setGid(42);
        $this->assertSame(42, StreamWrapper::getGid());
    }

    /**
     * @covers ::getDeviceName
     */
    public function testCanGetDeviceName() : void {
        $this->assertSame('<device>', StreamWrapper::getDeviceName());
    }

    /**
     * @covers ::rename
     */
    public function testRenameFailsWhenOriginDoesNotExist() : void {
        $this->assertFalse($this->ignoreError(fn () => rename('tfs://foo', 'tfs://bar/baz.txt')));
        $this->expectExceptionObject(new Warning('rename(tfs://foo,tfs://bar/baz.txt): No such file or directory'));
        rename('tfs://foo', 'tfs://bar/baz.txt');
    }

    /**
     * @covers ::rename
     */
    public function testRenameFailsWhenParentOfTargetDoesNotExist() : void {
        $this->assertTrue(touch('tfs://foo'));
        $this->assertFalse($this->ignoreError(fn () => rename('tfs://foo', 'tfs://bar/baz.txt')));

        $this->expectExceptionObject(new Warning('rename(tfs://foo,tfs://bar/baz.txt): No such file or directory'));
        rename('tfs://foo', 'tfs://bar/baz.txt');
    }

    /**
     * @covers ::rename
     */
    public function testRenameFailsWhenTargetIsADirectory() : void {
        $this->assertTrue(touch('tfs://foo'));
        $this->assertTrue(mkdir('tfs://bar'));
        $this->assertFalse($this->ignoreError(fn () => rename('tfs://foo', 'tfs://bar')));
        $this->expectExceptionObject(new Warning('rename(tfs://foo,tfs://bar): Is a directory'));
        rename('tfs://foo', 'tfs://bar');
    }

    /**
     * @covers ::rename
     */
    public function testRenameFailsWhenRenamingFromDirectoryToFile() : void {
        $this->assertTrue(mkdir('tfs://foo'));
        $this->assertTrue(touch('tfs://bar'));
        $this->assertFalse($this->ignoreError(fn () => rename('tfs://foo', 'tfs://bar')));

        $this->expectExceptionObject(new Warning('rename(tfs://foo,tfs://bar): Not a directory'));
        rename('tfs://foo', 'tfs://bar');
    }

    /**
     * @covers ::rename
     */
    public function testRenameOverwritesExistingTarget() : void {
        $this->assertTrue(touch('tfs://origin.txt'));
        $this->assertTrue(touch('tfs://target.txt'));

        $target = $this->device->getChild('target.txt');
        $this->assertInstanceOf(File::class, $target);

        $this->assertSame($this->device, $target->getParent());
        $this->assertTrue(rename('tfs://origin.txt', 'tfs://target.txt'), 'Expected rename to succeed');
        $this->assertNull($target->getParent(), 'Expected old target to get detached');
        $this->assertNull($this->device->getChild('origin.txt'), 'Expected origin to be gone');

        $newTarget = $this->device->getChild('target.txt');

        $this->assertNotSame($target, $newTarget, 'Did not expect the old target to be the same as the new target');
    }

    /**
     * @covers ::rename
     * @covers ::getAssetParent
     * @covers ::getAssetFromUrl
     * @covers ::getAsset
     */
    public function testCanRenameFile() : void {
        $this->assertTrue(touch('tfs://foo'));
        $this->assertTrue(touch('tfs://bar'));
        $this->assertTrue(mkdir('tfs://baz'));
        $this->assertTrue(touch('tfs://baz/bar'));

        $this->assertTrue(rename('tfs://foo', 'tfs://foobar'));

        $this->assertFalse($this->device->hasChild('foo'), '/foo should not exist');
        $this->assertTrue($this->device->hasChild('foobar'), '/foobar should exist');

        $this->assertTrue(rename('tfs://bar', 'tfs://baz/barfoo'));

        $this->assertFalse($this->device->hasChild('bar'), '/bar should not exist');
        $dir = $this->device->getChild('baz');
        $this->assertInstanceOf(Directory::class, $dir);
        $this->assertTrue($dir->hasChild('barfoo'), '/baz/barfoo should exist');
    }

    /**
     * @covers ::stream_open
     * @covers ::stream_eof
     */
    public function testCanCheckForEndOfFile() : void {
        $handle = $this->getHandleForFixture('tfs://foo.txt', 'r', FIXTURES_DIR . '/file.txt');
        $this->assertSame('this is a test file', trim((string) fgets($handle)));
        $this->assertFalse(feof($handle), 'Did not expect end of file');
        $this->assertSame('with multiple', trim((string) fgets($handle)));
        $this->assertFalse(feof($handle), 'Did not expect end of file');
        $this->assertSame('lines', trim((string) fgets($handle)));
        $this->assertTrue(feof($handle), 'Expected end of file');
    }

    /**
     * @covers ::stream_open
     * @covers ::stream_seek
     * @covers ::stream_tell
     */
    public function testCanSeekInFiles() : void {
        $handle = $this->getHandleForFixture('tfs://foo.txt', 'r', FIXTURES_DIR . '/file.txt');
        fseek($handle, 4, SEEK_SET);
        $this->assertSame(' is ', fread($handle, 4));
    }

    /**
     * @covers ::stream_truncate
     * @covers ::stream_eof
     */
    public function testCanTruncateFile() : void {
        $handle = $this->getHandleForFixture('tfs://foo.txt', 'r+', FIXTURES_DIR . '/file.txt');
        ftruncate($handle, 7);
        $this->assertSame('this is', fgets($handle));
        $this->assertTrue(feof($handle), 'Expected end of file');
    }

    /**
     * Get a file handle for a fixture
     *
     * @param string $url The name of the tfs file, for instance tfs://foo.txt
     * @param string $mode The mode to use when opening the file
     * @param string $fixturePath The path to the local fixture
     * @return resource Returns a file handle
     */
    private function getHandleForFixture(string $url, string $mode, string $fixturePath) {
        $fixture = file_get_contents($fixturePath);
        file_put_contents($url, $fixture);

        /** @var resource */
        return fopen($url, $mode);
    }

    /**
     * @covers ::parseFopenMode
     * @covers ::stream_open
     * @covers ::warn
     */
    public function testFopenFailsOnInvalidMode() : void {
        $this->assertFalse($this->ignoreError(fn () => fopen('tfs://foo.txt', 'z')));
        $this->expectExceptionObject(new Warning('fopen(): Unsupported mode: "z"'));
        fopen('tfs://foo.txt', 'z');
    }

    /**
     * @covers ::stream_open
     */
    public function testFopenFailsWhenUsingPathOption() : void {
        $this->assertFalse($this->ignoreError(fn () => fopen('tfs://foo.txt', 'w', true)));
        $this->expectExceptionObject(new Warning('TestFs does not support "use_include_path"'));
        fopen('tfs://foo.txt', 'w', true);
    }

    /**
     * @covers ::stream_open
     */
    public function testFopenFailsWhenOpeningAFileForWritingAndTheParentDoesNotExist() : void {
        $this->assertFalse($this->ignoreError(fn () => fopen('tfs://foo/bar.txt', 'w')));
        $this->expectExceptionObject(new Warning('fopen(foo/bar.txt): failed to open stream: No such file or directory'));
        fopen('tfs://foo/bar.txt', 'w');
    }

    /**
     * @covers ::stream_open
     */
    public function testFopenFailsWhenOpeningADirectory() : void {
        mkdir('tfs://foo');
        $this->assertFalse($this->ignoreError(fn () => fopen('tfs://foo', 'w')));
        $this->expectExceptionObject(new Warning('fopen(foo): failed to open stream. Is a directory'));
        fopen('tfs://foo', 'w');
    }

    /**
     * @covers ::stream_open
     */
    public function testFopenFailsWhenOpeningAFileThatDoesNotExistWithoutCreationMode() : void {
        $this->assertFalse($this->ignoreError(fn () => fopen('tfs://foo.txt', 'r')));
        $this->expectExceptionObject(new Warning('fopen(foo.txt): failed to open stream: No such file or directory'));
        fopen('tfs://foo.txt', 'r');
    }

    /**
     * @covers ::stream_open
     */
    public function testFopenCanOpenFilesUsingAppendMode() : void {
        file_put_contents('tfs://file.txt', 'one');
        $fp = fopen('tfs://file.txt', 'a');
        fwrite($fp, 'two');
        fclose($fp);

        $this->assertSame('onetwo', file_get_contents('tfs://file.txt'));
    }

    /**
     * @covers ::stream_open
     */
    public function testFopenCanCreateFiles() : void {
        $fp = fopen('tfs://file.txt', 'w');
        fwrite($fp, 'some text');
        fclose($fp);

        $this->assertSame('some text', file_get_contents('tfs://file.txt'));
    }

    /**
     * @covers ::stream_lock
     * @covers ::getLockId
     */
    public function testCanLockFiles() : void {
        touch('tfs://foo.txt');

        $handle = fopen('tfs://foo.txt', 'w');
        $this->assertTrue(flock($handle, LOCK_EX), 'Expected to get exclusive lock');

        $newHandle = fopen('tfs://foo.txt', 'w');
        $this->assertFalse(flock($newHandle, LOCK_EX), 'Did not expect to get exclusive lock');
    }

    /**
     * @covers ::stream_lock
     */
    public function testSilentlyIgnoresNonBlockingOption() : void {
        touch('tfs://foo.txt');

        $handle = fopen('tfs://foo.txt', 'w');
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB), 'Expected to get exclusive lock');

        /** @var File */
        $file = $this->device->getChild('foo.txt');

        $this->assertTrue($file->isLocked(), 'Expected file to be locked');
    }

    /**
     * @covers ::url_stat
     */
    public function testStatFailsWhenAssetDoesNotExist() : void {
        $this->assertFalse($this->ignoreError(fn () => stat('tfs://foo.txt')));
        $this->expectExceptionObject(new Warning('stat(): stat failed for tfs://foo.txt'));
        $_ = stat('tfs://foo.txt');
    }

    /**
     * @covers ::url_stat
     */
    public function testStatCanFailQuietlyWhenAssetDoesNotExist() : void {
        $this->assertFalse($this->ignoreError(fn () => stat('tfs://foo.txt')));
        $this->expectExceptionObject(new Warning('stat(): stat failed for tfs://foo.txt'));
        $_ = stat('tfs://foo.txt');
    }

    /**
     * @covers ::url_stat
     */
    public function testStatFailsWhenParentDirIsNotReadable() : void {
        mkdir('tfs://dir', 0770);
        touch('tfs://dir/file');

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => stat('tfs://dir/file')));
        $this->expectExceptionObject(new Warning('stat(): stat failed for tfs://dir/file'));
        $_ = stat('tfs://dir/file');
    }

    /**
     * @covers ::stream_metadata
     */
    public function testTouchFailsWhenParentDirectoryDoesNotExist() : void {
        $this->assertFalse($this->ignoreError(fn () => touch('tfs://foo/bar.txt')));
        $this->expectExceptionObject(new Warning('touch(): Unable to create file foo/bar.txt because No such file or directory'));
        touch('tfs://foo/bar.txt');
    }

    /**
     * @covers ::stream_metadata
     */
    public function testCanChangeAssetMode() : void {
        $this->assertTrue(mkdir('tfs://foo'));
        $this->assertTrue(touch('tfs://foo/bar.txt'));

        $this->assertTrue(chmod('tfs://foo', 0644));
        $this->assertTrue(chmod('tfs://foo/bar.txt', 0600));

        /** @var Directory */
        $foo = $this->device->getChild('foo');
        $this->assertSame(0644, $foo->getMode());

        /** @var File */
        $bar = $foo->getChild('bar.txt');
        $this->assertSame(0600, $bar->getMode());
    }

    /**
     * @covers ::stream_metadata
     */
    public function testFailsWhenChangingModeOnNonExistingFile() : void {
        $this->assertFalse($this->ignoreError(fn () => chmod('tfs://foo/bar.txt', 0600)));
        $this->expectExceptionObject(new Warning('chmod(): No such file or directory'));
        chmod('tfs://foo/bar.txt', 0600);
    }

    /**
     * @covers ::stream_metadata
     */
    public function testTouchSupportsCustomTimes() : void {
        touch('tfs://file.txt', 123, 456);

        /** @var File */
        $file = $this->device->getChild('file.txt');
        $this->assertSame(123, $file->getLastModified());
        $this->assertSame(456, $file->getLastAccessed());
    }

    /**
     * @covers ::stream_metadata
     */
    public function testChangeOwnerWithNonExistingUsername() : void {
        touch('tfs://file.txt');
        $this->assertFalse($this->ignoreError(fn () => chown('tfs://file.txt', 'non-exsiting-user')));
        $this->expectExceptionObject(new Warning('chown(): Unable to find uid for non-exsiting-user'));
        chown('tfs://file.txt', 'non-exsiting-user');
    }

    /**
     * @covers ::stream_metadata
     */
    public function testChangeOwnerWithNonExistingUid() : void {
        touch('tfs://file.txt');
        $this->assertFalse($this->ignoreError(fn () => chown('tfs://file.txt', 123)));
        $this->expectExceptionObject(new Warning('chown(): Operation not permitted'));
        chown('tfs://file.txt', 123);
    }

    /**
     * @covers ::stream_metadata
     */
    public function testChangeOwnerFailsWhenFileDoesNotExist() : void {
        $this->assertFalse($this->ignoreError(fn () => chown('tfs://file.txt', 123)));
        $this->expectExceptionObject(new Warning('chown(): No such file or directory'));
        chown('tfs://file.txt', 123);
    }

    /**
     * @covers ::stream_metadata
     */
    public function testChownFailsWhenRegularUserIsNotOwner() : void {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addUser(2, 'user2');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::addGroup(2, 'group2', [2]);

        StreamWrapper::setUid(1);

        $this->assertTrue(touch('tfs://file.txt'), 'Expected touch to succeed');
        $this->assertFalse($this->ignoreError(fn () => chown('tfs://file.txt', 'user2')), 'Expected chown to fail');
        $this->expectExceptionObject(new Warning('chown(): Operation not permitted'));
        chown('tfs://file.txt', 'user2');
    }

    /**
     * @covers ::stream_metadata
     */
    public function testCanChangeOwnerToSelfWhenAlreadyOwningFile() : void {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);

        StreamWrapper::setUid(1);

        $this->assertTrue(touch('tfs://file.txt'), 'Expected touch to succeed');
        $this->assertTrue(chown('tfs://file.txt', 'user1'), 'Expected chown to succeed');
    }

    /**
     * @covers ::stream_metadata
     */
    public function testChangeGroupWithNonExistingGroup() : void {
        touch('tfs://file.txt');
        $this->assertFalse($this->ignoreError(fn () => chgrp('tfs://file.txt', 'non-exsiting-group')));
        $this->expectExceptionObject(new Warning('chgrp(): Unable to find gid for non-exsiting-group'));
        chgrp('tfs://file.txt', 'non-exsiting-group');
    }

    /**
     * @covers ::stream_metadata
     */
    public function testChangeGroupWithNonExistingGid() : void {
        touch('tfs://file.txt');
        $this->assertFalse($this->ignoreError(fn () => chgrp('tfs://file.txt', 123)));
        $this->expectExceptionObject(new Warning('chgrp(): Operation not permitted'));
        chgrp('tfs://file.txt', 123);
    }

    /**
     * @covers ::stream_metadata
     */
    public function testChangeGroupFailsWhenFileDoesNotExist() : void {
        $this->assertFalse($this->ignoreError(fn () => chgrp('tfs://file.txt', 123)));
        $this->expectExceptionObject(new Warning('chgrp(): No such file or directory'));
        chgrp('tfs://file.txt', 123);
    }

    /**
     * @covers ::stream_metadata
     * @covers ::addUser
     * @covers ::addGroup
     */
    public function testCanChangeAssetOwnerAndGroup() : void {
        touch('tfs://file.txt');

        /** @var File */
        $asset = $this->device->getChild('file.txt');

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addUser(2, 'user2');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::addGroup(2, 'group2', [2]);

        $this->assertSame(0, $asset->getUid(), 'Expected uid to be 0');
        $this->assertSame(0, $asset->getGid(), 'Expected gid to be 0');

        $this->assertTrue(chown('tfs://file.txt', 'user1'));
        $this->assertSame(1, $asset->getUid(), 'Expected uid to be 1');

        $this->assertTrue(chown('tfs://file.txt', 2));
        $this->assertSame(2, $asset->getUid(), 'Expected uid to be 2');

        $this->assertTrue(chgrp('tfs://file.txt', 'group1'));
        $this->assertSame(1, $asset->getGid(), 'Expected gid to be 1');

        $this->assertTrue(chgrp('tfs://file.txt', 2));
        $this->assertSame(2, $asset->getGid(), 'Expected gid to be 2');
    }

    /**
     * @covers ::addUser
     */
    public function testThrowsExceptionWhenAddingExistingUser() : void {
        StreamWrapper::addUser(1, 'name');
        $this->expectExceptionObject(new InvalidArgumentException('User with uid 1 already exists'));
        StreamWrapper::addUser(1, 'name');
    }

    /**
     * @covers ::addGroup
     */
    public function testThrowsExceptionWhenAddingExistingGroup() : void {
        StreamWrapper::addGroup(1, 'name');
        $this->expectExceptionObject(new InvalidArgumentException('Group with gid 1 already exists'));
        StreamWrapper::addGroup(1, 'name');
    }

    /**
     * @covers ::dir_opendir
     */
    public function testOpeningDirectoryWithoutPermission() : void {
        mkdir('tfs://root', 0770);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => opendir('tfs://root')));

        $this->expectExceptionObject(new Warning('opendir(tfs://root): failed to open dir: Permission denied'));
        opendir('tfs://root');
    }

    /**
     * @covers ::dir_opendir
     */
    public function testOpeningDirectoryInProtectedDirectoryFails() : void {
        mkdir('tfs://root/dir', 0770, true);
        chmod('tfs://root/dir', 0777);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => opendir('tfs://root/dir')));

        $this->expectExceptionObject(new Warning('opendir(tfs://root/dir): failed to open dir: Permission denied'));
        opendir('tfs://root/dir');
    }

    /**
     * @covers ::mkdir
     */
    public function testMkdirFailsWhenCreatingADirectoryInANonWritableDirectory() : void {
        mkdir('tfs://root', 0770);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => mkdir('tfs://root/dir')));

        $this->expectExceptionObject(new Warning('mkdir(): Permission denied'));
        mkdir('tfs://root/dir');
    }

    /**
     * @covers ::stream_open
     */
    public function testCanNotReadFromFileOpenedInWriteMode() : void {
        $fp = fopen('tfs://file.txt', 'w');
        fwrite($fp, 'this is some content');
        fseek($fp, 0);
        $this->assertFalse(fgets($fp), 'Expected fgets to return false');
    }

    /**
     * @covers ::stream_open
     */
    public function testCanNotWriteToFileOpenedInReadOnlyMode() : void {
        file_put_contents('tfs://file.txt', 'this is some content');
        $fp = fopen('tfs://file.txt', 'r');
        $this->assertSame(0, fwrite($fp, 'new content'), 'Expected fwrite to return 0');
    }

    /**
     * @covers ::stream_open
     */
    public function testOpenAndCreatingFileInUnwritableDirectoryFails() : void {
        mkdir('tfs://dir', 0770);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);


        $this->assertFalse($this->ignoreError(fn () => fopen('tfs://dir/file', 'w+')), 'Expected fopen to fail');

        $this->expectExceptionObject(new Warning('fopen(dir/file): failed to open stream: Permission denied'));
        fopen('tfs://dir/file', 'w+');
    }

    /**
     * @covers ::stream_open
     */
    public function testOpeningUnreadableFileFails() : void {
        mkdir('tfs://dir');

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        touch('tfs://dir/file');
        chmod('tfs://dir/file', 0000);

        $this->assertFalse($this->ignoreError(fn () => fopen('tfs://dir/file', 'r')), 'Expected fopen to fail');

        $this->expectExceptionObject(new Warning('fopen(dir/file): failed to open stream: Permission denied'));
        fopen('tfs://dir/file', 'r');
    }

    /**
     * @covers ::stream_metadata
     */
    public function testCanChangeGroupToGroupWhichUserIsAMember() : void {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::addGroup(2, 'group2', [1]);

        StreamWrapper::setUid(1);

        $this->assertTrue(touch('tfs://file.txt'), 'Expected touch to succeed');
        $this->assertTrue(chgrp('tfs://file.txt', 'group1'), 'Expected chgrp to succeed');
        $this->assertTrue(chgrp('tfs://file.txt', 'group2'), 'Expected chgrp to succeed');
    }

    /**
     * @covers ::stream_metadata
     * @covers ::userIsInGroup
     */
    public function testCanNotChangeToGroupWhenUserIsNotAMember() : void {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addUser(2, 'user2');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::addGroup(2, 'group2', [2]);

        StreamWrapper::setUid(1);

        $this->assertTrue(touch('tfs://file.txt'), 'Expected touch to succeed');
        $this->assertTrue(chgrp('tfs://file.txt', 'group1'), 'Expected chgrp to succeed');
        $this->assertFalse($this->ignoreError(fn () => chgrp('tfs://file.txt', 'group2')), 'Expected chgrp to fail');

        $this->expectExceptionObject(new Warning('chgrp(): Operation not permitted'));
        chgrp('tfs://file.txt', 'group2');
    }

    /**
     * @covers ::stream_metadata
     * @covers ::userIsInGroup
     */
    public function testCanNotChangeToGroupWhenGroupIsEmpty() : void {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1');
        StreamWrapper::setUid(1);

        $this->assertTrue(touch('tfs://file.txt'), 'Expected touch to succeed');

        $this->expectExceptionObject(new Warning('chgrp(): Operation not permitted'));
        chgrp('tfs://file.txt', 'group1');
    }

    /**
     * @covers ::dir_readdir
     */
    public function testThrowsExceptionOnMissingDirectoryIteratorWhenReading() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid directory iterator'));
        (new StreamWrapper())->dir_readdir();
    }

    /**
     * @covers ::dir_rewinddir
     */
    public function testThrowsExceptionOnMissingDirectoryIteratorWhenRewinding() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid directory iterator'));
        (new StreamWrapper())->dir_rewinddir();
    }

    /**
     * @covers ::stream_close
     */
    public function testThrowsExceptionOnMissingFileHandleWhenClosingStream() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_close();
    }

    /**
     * @covers ::stream_eof
     */
    public function testThrowsExceptionOnMissingFileHandleWhenCheckingEof() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_eof();
    }

    /**
     * @covers ::stream_lock
     */
    public function testThrowsExceptionOnMissingFileHandleWhenLockingStream() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_lock(LOCK_EX);
    }

    /**
     * @covers ::stream_read
     */
    public function testThrowsExceptionOnMissingFileHandleWhenReadingStream() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_read(10);
    }

    /**
     * @covers ::stream_seek
     */
    public function testThrowsExceptionOnMissingFileHandleWhenSeekingInStream() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_seek(10);
    }

    /**
     * @covers ::stream_stat
     */
    public function testThrowsExceptionOnMissingFileHandleWhenDoingStreamStat() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_stat();
    }

    /**
     * @covers ::stream_tell
     */
    public function testThrowsExceptionOnMissingFileHandleWhenFetchingOffset() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_tell();
    }

    /**
     * @covers ::stream_truncate
     */
    public function testThrowsExceptionOnMissingFileHandleWhenTruncatingStream() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_truncate(10);
    }

    /**
     * @covers ::stream_write
     */
    public function testThrowsExceptionOnMissingFileHandleWhenWritingToStream() : void {
        $this->expectExceptionObject(new RuntimeException('Invalid file handle'));
        (new StreamWrapper())->stream_write('some data');
    }

    /**
     * @return array<array{url:string,expectedPath:string}>
     */
    public static function getUrls() : array {
        return [
            [
                'url' => 'tfs://foo',
                'expectedPath' => 'foo'
            ],
            [
                'url' => 'tfs://foo//bar\\baz',
                'expectedPath' => 'foo/bar/baz'
            ]
        ];
    }

    /**
     * @return array<string,array{path:string,expectedUrl:string}>
     */
    public static function getPaths() : array {
        return [
            'relative' => [
                'path' => 'foo',
                'expectedUrl' => 'tfs://foo'
            ],
            'absolute' => [
                'path' => '/foo/bar/baz.txt',
                'expectedUrl' => 'tfs://foo/bar/baz.txt'
            ],
        ];
    }
}
