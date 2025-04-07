<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TestFs\Exception\DuplicateGroupException;
use TestFs\Exception\DuplicateUserException;
use TestFs\Exception\InvalidUrlException;
use TestFs\Exception\ProtocolAlreadyRegisteredException;
use TestFs\Exception\UnknownGroupException;
use TestFs\Exception\UnknownUserException;

#[CoversClass(StreamWrapper::class)]
class StreamWrapperTest extends TestCase
{
    use ErrorHandler;

    private RootDirectory $root;

    protected function setUp(): void
    {
        if (!StreamWrapper::register()) {
            $this->fail('Unable to register streamwrapper');
        }

        $device = StreamWrapper::getDevice();

        if (null === $device) {
            $this->fail('Wrapper has not been properly initialized');
        }

        $this->root = $device->getRoot();
    }

    protected function tearDown(): void
    {
        StreamWrapper::unregister();
    }

    public function testCanNotRegisterTwice(): void
    {
        $this->expectException(ProtocolAlreadyRegisteredException::class);
        StreamWrapper::register();
    }

    public function testCanForceRegister(): void
    {
        $this->assertTrue(StreamWrapper::register(true));
    }

    public function testCanCreateDirectory(): void
    {
        $this->assertFalse($this->root->hasChild('foobar'));
        $this->assertTrue(mkdir('foobar'));
        $this->assertTrue($this->root->hasChild('foobar'));
        $this->assertInstanceOf(Directory::class, $this->root->getChild('foobar'));
    }

    public function testCanCreateDirectoryRecursively(): void
    {
        $this->assertTrue(mkdir('foo/bar/baz', 0777, true));
        $child = $this->root->getDirectory('foo');
        $this->assertInstanceOf(Directory::class, $child);

        $child = $child->getDirectory('bar');
        $this->assertInstanceOf(Directory::class, $child);

        $this->assertTrue($child->hasChild('baz'));
        $this->assertInstanceOf(Directory::class, $child->getChild('baz'));
    }

    public function testMkdirFailsWhenNameExists(): void
    {
        $this->assertTrue(mkdir('foobar'));
        $this->assertFalse($this->ignoreError(fn () => mkdir('foobar')));
        $this->expectExceptionObject(new Warning('mkdir(): File exists'));
        mkdir('foobar');
    }

    public function testMkdirFailsOnNonRecursiveWhenADirIsMissing(): void
    {
        $this->assertFalse($this->ignoreError(fn () => mkdir('foo/bar')));
        $this->expectExceptionObject(new Warning('mkdir(): No such file or directory'));
        mkdir('foo/bar');
    }

    public function testCanCreateDirsWhenSomeDirsExist(): void
    {
        $this->assertTrue(mkdir('foo'));
        $this->assertTrue(mkdir('foo/bar'));

        $dir = $this->root->getDirectory('foo');
        $this->assertNotNull($dir);
        $this->assertTrue($dir->hasChild('bar'));
        $this->assertInstanceOf(Directory::class, $dir->getChild('bar'));
    }

    public function testCanRemoveDir(): void
    {
        $this->assertTrue(mkdir('foo'));
        $this->assertTrue($this->root->hasChild('foo'));
        $this->assertTrue(rmdir('foo'));
        $this->assertFalse($this->root->hasChild('foo'));
    }

    public function testRmDirFailsWhenDeletingANonExistingDir(): void
    {
        $this->assertFalse($this->ignoreError(fn () => rmdir('foo')));
        $this->expectExceptionObject(new Warning('rmdir(foo): No such file or directory'));
        rmdir('foo');
    }

    public function testRmDirFailsWhenDeletingANonDirectory(): void
    {
        $this->assertTrue(touch('foo'));
        $this->assertFalse($this->ignoreError(fn () => rmdir('foo')));
        $this->expectExceptionObject(new Warning('rmdir(foo): Not a directory'));
        rmdir('foo');
    }

    public function testRmDirFailsWhenDeletingANonEmptyDirectory(): void
    {
        $this->assertTrue(mkdir('foo'));
        $this->assertTrue(touch('foo/bar'));
        $this->assertFalse($this->ignoreError(fn () => rmdir('foo')));
        $this->expectExceptionObject(new Warning('rmdir(foo): Not empty'));
        rmdir('foo');
    }

    public function testRmDirFailsOnMissingPermissions(): void
    {
        mkdir('root', 0770);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => rmdir('root')));

        $this->expectExceptionObject(new Warning('rmdir(root): Permission denied'));
        rmdir('root');
    }

    public function testCanOpenAndReadDirectories(): void
    {
        $this->assertTrue(mkdir('foo/bar', 0777, true));

        $entries = [
            'foo/bar/baz.txt' => ['type' => 'dir', 'expectedName' => 'bar'],
            'foo/bar.txt' => ['type' => 'file', 'expectedName' => 'bar.txt'],
            'foo/baz.txt' => ['type' => 'file', 'expectedName' => 'baz.txt'],
        ];

        foreach (array_keys($entries) as $name) {
            touch(sprintf($name));
        }

        $path = 'foo';
        $handle = opendir($path);

        $this->assertIsResource($handle);

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

    public function testFailsWhenOpeningDirectoryThatDoesNotExist(): void
    {
        $this->assertFalse($this->ignoreError(fn () => opendir('foo')));
        $this->expectExceptionObject(new Warning('opendir(tfs://foo): failed to open dir: No such file or directory'));
        opendir('foo');
    }

    public function testFailsWhenOpeningFileAsDir(): void
    {
        touch('foo');
        $this->assertFalse($this->ignoreError(fn () => opendir('foo')));
        $this->expectExceptionObject(new Warning('opendir(tfs://foo): failed to open dir: Not a directory'));
        opendir('foo');
    }

    #[DataProvider('getUrls')]
    public function testCanConvertUrlToPath(string $url, string $expectedPath): void
    {
        $this->assertSame($expectedPath, (new StreamWrapper())->urlToPath($url));
    }

    public function testUrlToPathFailsOnInvalidUrl(): void
    {
        $this->expectExceptionObject(new InvalidUrlException('foo://bar'));
        (new StreamWrapper())->urlToPath('foo://bar');
    }

    #[DataProvider('getPaths')]
    public function testCanConvertPathToUrl(string $path, string $expectedUrl): void
    {
        $this->assertSame($expectedUrl, (new StreamWrapper())->url($path));
    }

    public function testCanRemoveFile(): void
    {
        touch('foo.bar');
        $this->assertTrue(unlink('foo.bar'));
    }

    public function testRemoveFileThatDoesNotExistFails(): void
    {
        $this->assertFalse($this->ignoreError(fn () => unlink('foo.bar')));
        $this->expectExceptionObject(new Warning('unlink(foo.bar): No such file or directory'));
        unlink('foo.bar');
    }

    public function testUnlinkDirectoryFails(): void
    {
        mkdir('foo');
        $this->assertFalse($this->ignoreError(fn () => unlink('foo')));

        $this->expectExceptionObject(new Warning('unlink(foo): Is a directory'));
        unlink('foo');
    }

    public function testUnlinkFailsWhenDirIsNotWritable(): void
    {
        mkdir('dir', 0770);
        touch('dir/file');

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => unlink('dir/file')));

        $this->expectExceptionObject(new Warning('unlink(dir/file): Permission denied'));
        unlink('dir/file');
    }

    public function testCanWriteAndReadCompleteFiles(): void
    {
        $handle = $this->getHandleForFixture('foo.txt', 'r', FIXTURES_DIR . '/file.txt');
        $this->assertTrue(fclose($handle));
        $this->assertSame(\file_get_contents(FIXTURES_DIR . '/file.txt'), file_get_contents('foo.txt'));
    }

    public function testThrowsExceptionWhenSettingUidThatDoesNotExist(): void
    {
        $this->expectExceptionObject(new UnknownUserException(42));
        StreamWrapper::setUid(42);
    }

    public function testThrowsExceptionWhenSettingGidThatDoesNotExist(): void
    {
        $this->expectExceptionObject(new UnknownGroupException(42));
        StreamWrapper::setGid(42);
    }

    public function testCanSetAndGetUid(): void
    {
        $this->assertSame(0, StreamWrapper::getUid());
        StreamWrapper::addUser(42, 'user');
        StreamWrapper::setUid(42);
        $this->assertSame(42, StreamWrapper::getUid());
    }

    public function testCanGetGid(): void
    {
        $this->assertSame(0, StreamWrapper::getGid());
        StreamWrapper::addGroup(42, 'group');
        StreamWrapper::setGid(42);
        $this->assertSame(42, StreamWrapper::getGid());
    }

    public function testRenameFailsWhenOriginDoesNotExist(): void
    {
        $this->assertFalse($this->ignoreError(fn () => rename('foo', 'bar/baz.txt')));
        $this->expectExceptionObject(new Warning('rename(tfs://foo,tfs://bar/baz.txt): No such file or directory'));
        rename('foo', 'bar/baz.txt');
    }

    public function testRenameFailsWhenParentOfTargetDoesNotExist(): void
    {
        $this->assertTrue(touch('foo'));
        $this->assertFalse($this->ignoreError(fn () => rename('foo', 'bar/baz.txt')));

        $this->expectExceptionObject(new Warning('rename(tfs://foo,tfs://bar/baz.txt): No such file or directory'));
        rename('foo', 'bar/baz.txt');
    }

    public function testRenameFailsWhenTargetIsADirectory(): void
    {
        $this->assertTrue(touch('foo'));
        $this->assertTrue(mkdir('bar'));
        $this->assertFalse($this->ignoreError(fn () => rename('foo', 'bar')));
        $this->expectExceptionObject(new Warning('rename(tfs://foo,tfs://bar): Is a directory'));
        rename('foo', 'bar');
    }

    public function testRenameFailsWhenRenamingFromDirectoryToFile(): void
    {
        $this->assertTrue(mkdir('foo'));
        $this->assertTrue(touch('bar'));
        $this->assertFalse($this->ignoreError(fn () => rename('foo', 'bar')));

        $this->expectExceptionObject(new Warning('rename(tfs://foo,tfs://bar): Not a directory'));
        rename('foo', 'bar');
    }

    public function testRenameOverwritesExistingTarget(): void
    {
        $this->assertTrue(touch('origin.txt'));
        $this->assertTrue(touch('target.txt'));

        $target = $this->root->getChild('target.txt');
        $this->assertInstanceOf(File::class, $target);

        $this->assertSame($this->root, $target->getParent());
        $this->assertTrue(rename('origin.txt', 'target.txt'), 'Expected rename to succeed');
        $this->assertNull($target->getParent(), 'Expected old target to get detached');
        $this->assertNull($this->root->getChild('origin.txt'), 'Expected origin to be gone');

        $newTarget = $this->root->getChild('target.txt');

        $this->assertNotSame($target, $newTarget, 'Did not expect the old target to be the same as the new target');
    }

    public function testCanRenameFile(): void
    {
        $this->assertTrue(touch('foo'));
        $this->assertTrue(touch('bar'));
        $this->assertTrue(mkdir('baz'));
        $this->assertTrue(touch('baz/bar'));

        $this->assertTrue(rename('foo', 'foobar'));

        $this->assertFalse($this->root->hasChild('foo'), '/foo should not exist');
        $this->assertTrue($this->root->hasChild('foobar'), '/foobar should exist');

        $this->assertTrue(rename('bar', 'baz/barfoo'));

        $this->assertFalse($this->root->hasChild('bar'), '/bar should not exist');
        $dir = $this->root->getDirectory('baz');
        $this->assertNotNull($dir);
        $this->assertTrue($dir->hasChild('barfoo'), '/baz/barfoo should exist');
    }

    public function testCanCheckForEndOfFile(): void
    {
        $handle = $this->getHandleForFixture('foo.txt', 'r', FIXTURES_DIR . '/file.txt');
        $this->assertSame('this is a test file', trim((string) fgets($handle)));
        $this->assertFalse(feof($handle), 'Did not expect end of file');
        $this->assertSame('with multiple', trim((string) fgets($handle)));
        $this->assertFalse(feof($handle), 'Did not expect end of file');
        $this->assertSame('lines', trim((string) fgets($handle)));
        $this->assertTrue(feof($handle), 'Expected end of file');
    }

    public function testCanSeekInFiles(): void
    {
        $handle = $this->getHandleForFixture('foo.txt', 'r', FIXTURES_DIR . '/file.txt');
        fseek($handle, 4, SEEK_SET);
        $this->assertSame(' is ', fread($handle, 4));
    }

    public function testCanTruncateFile(): void
    {
        $handle = $this->getHandleForFixture('foo.txt', 'r+', FIXTURES_DIR . '/file.txt');
        ftruncate($handle, 7);
        $this->assertSame('this is', fgets($handle));
        $this->assertTrue(feof($handle), 'Expected end of file');
    }

    /**
     * Get a file handle for a fixture
     *
     * @param string $url The name of the tfs file, for instance foo.txt
     * @param string $mode The mode to use when opening the file
     * @param string $fixturePath The path to the local fixture
     * @return resource Returns a file handle
     */
    private function getHandleForFixture(string $url, string $mode, string $fixturePath)
    {
        $fixture = \file_get_contents($fixturePath);
        file_put_contents($url, $fixture);

        /** @var resource */
        return fopen($url, $mode);
    }

    public function testFopenFailsOnInvalidMode(): void
    {
        $this->assertFalse($this->ignoreError(fn () => fopen('foo.txt', 'z')));
        $this->expectExceptionObject(new Warning('fopen(): Unsupported mode: "z"'));
        fopen('foo.txt', 'z');
    }

    public function testFopenFailsWhenUsingPathOption(): void
    {
        $this->assertFalse($this->ignoreError(fn () => fopen('foo.txt', 'w', true)));
        $this->expectExceptionObject(new Warning('TestFs does not support "use_include_path"'));
        fopen('foo.txt', 'w', true);
    }

    public function testFopenFailsWhenOpeningAFileForWritingAndTheParentDoesNotExist(): void
    {
        $this->assertFalse($this->ignoreError(fn () => fopen('foo/bar.txt', 'w')));
        $this->expectExceptionObject(new Warning('fopen(foo/bar.txt): failed to open stream: No such file or directory'));
        fopen('foo/bar.txt', 'w');
    }

    public function testFopenFailsWhenOpeningADirectory(): void
    {
        mkdir('foo');
        $this->assertFalse($this->ignoreError(fn () => fopen('foo', 'w')));
        $this->expectExceptionObject(new Warning('fopen(foo): failed to open stream. Is a directory'));
        fopen('foo', 'w');
    }

    public function testFopenFailsWhenOpeningAFileThatDoesNotExistWithoutCreationMode(): void
    {
        $this->assertFalse($this->ignoreError(fn () => fopen('foo.txt', 'r')));
        $this->expectExceptionObject(new Warning('fopen(foo.txt): failed to open stream: No such file or directory'));
        fopen('foo.txt', 'r');
    }

    public function testFopenCanOpenFilesUsingAppendMode(): void
    {
        file_put_contents('file.txt', 'one');
        $fp = fopen('file.txt', 'a');
        $this->assertIsResource($fp);
        fwrite($fp, 'two');
        fclose($fp);

        $this->assertSame('onetwo', file_get_contents('file.txt'));
    }

    public function testFopenCanCreateFiles(): void
    {
        $fp = fopen('file.txt', 'w');
        $this->assertIsResource($fp);
        fwrite($fp, 'some text');
        fclose($fp);

        $this->assertSame('some text', file_get_contents('file.txt'));
    }

    public function testCanLockFiles(): void
    {
        touch('foo.txt');

        $handle = fopen('foo.txt', 'w');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX), 'Expected to get exclusive lock');

        $newHandle = fopen('foo.txt', 'w');
        $this->assertIsResource($newHandle);
        $this->assertFalse(flock($newHandle, LOCK_EX), 'Did not expect to get exclusive lock');
    }

    public function testSilentlyIgnoresNonBlockingOption(): void
    {
        touch('foo.txt');

        $handle = fopen('foo.txt', 'w');
        $this->assertIsResource($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB), 'Expected to get exclusive lock');

        /** @var File */
        $file = $this->root->getChild('foo.txt');

        $this->assertTrue($file->isLocked(), 'Expected file to be locked');
    }

    public function testStatFailsWhenAssetDoesNotExist(): void
    {
        $this->assertFalse($this->ignoreError(fn () => stat('foo.txt')));
        $this->expectExceptionObject(new Warning('stat(): stat failed for tfs://foo.txt'));
        $_ = stat('foo.txt');
    }

    public function testStatCanFailQuietlyWhenAssetDoesNotExist(): void
    {
        $this->assertFalse($this->ignoreError(fn () => stat('foo.txt')));
        $this->expectExceptionObject(new Warning('stat(): stat failed for tfs://foo.txt'));
        $_ = stat('foo.txt');
    }

    public function testStatFailsWhenParentDirIsNotReadable(): void
    {
        mkdir('dir', 0770);
        touch('dir/file');

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => stat('dir/file')));
        $this->expectExceptionObject(new Warning('stat(): stat failed for tfs://dir/file'));
        $_ = stat('dir/file');
    }

    public function testTouchFailsWhenParentDirectoryDoesNotExist(): void
    {
        $this->assertFalse($this->ignoreError(fn () => touch('foo/bar.txt')));
        $this->expectExceptionObject(new Warning('touch(): Unable to create file foo/bar.txt because No such file or directory'));
        touch('foo/bar.txt');
    }

    public function testCanChangeAssetMode(): void
    {
        $this->assertTrue(mkdir('foo'));
        $this->assertTrue(touch('foo/bar.txt'));

        $this->assertTrue(chmod('foo', 0644));
        $this->assertTrue(chmod('foo/bar.txt', 0600));

        /** @var Directory */
        $foo = $this->root->getChild('foo');
        $this->assertSame(0644, $foo->getMode());

        /** @var File */
        $bar = $foo->getChild('bar.txt');
        $this->assertSame(0600, $bar->getMode());
    }

    public function testFailsWhenChangingModeOnNonExistingFile(): void
    {
        $this->assertFalse($this->ignoreError(fn () => chmod('foo/bar.txt', 0600)));
        $this->expectExceptionObject(new Warning('chmod(): No such file or directory'));
        chmod('foo/bar.txt', 0600);
    }

    public function testTouchSupportsCustomTimes(): void
    {
        touch('file.txt', 123, 456);

        /** @var File */
        $file = $this->root->getChild('file.txt');
        $this->assertSame(123, $file->getLastModified());
        $this->assertSame(456, $file->getLastAccessed());
    }

    public function testChangeOwnerWithNonExistingUsername(): void
    {
        touch('file.txt');
        $this->assertFalse($this->ignoreError(fn () => chown('file.txt', 'non-exsiting-user')));
        $this->expectExceptionObject(new Warning('chown(): Unable to find uid for non-exsiting-user'));
        chown('file.txt', 'non-exsiting-user');
    }

    public function testChangeOwnerWithNonExistingUid(): void
    {
        touch('file.txt');
        $this->assertFalse($this->ignoreError(fn () => chown('file.txt', 123)));
        $this->expectExceptionObject(new Warning('chown(): Operation not permitted'));
        chown('file.txt', 123);
    }

    public function testChangeOwnerFailsWhenFileDoesNotExist(): void
    {
        $this->assertFalse($this->ignoreError(fn () => chown('file.txt', 123)));
        $this->expectExceptionObject(new Warning('chown(): No such file or directory'));
        chown('file.txt', 123);
    }

    public function testChownFailsWhenRegularUserIsNotOwner(): void
    {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addUser(2, 'user2');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::addGroup(2, 'group2', [2]);

        StreamWrapper::setUid(1);

        $this->assertTrue(touch('file.txt'), 'Expected touch to succeed');
        $this->assertFalse($this->ignoreError(fn () => chown('file.txt', 'user2')), 'Expected chown to fail');
        $this->expectExceptionObject(new Warning('chown(): Operation not permitted'));
        chown('file.txt', 'user2');
    }

    public function testCanChangeOwnerToSelfWhenAlreadyOwningFile(): void
    {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);

        StreamWrapper::setUid(1);

        $this->assertTrue(touch('file.txt'), 'Expected touch to succeed');
        $this->assertTrue(chown('file.txt', 'user1'), 'Expected chown to succeed');
    }

    public function testChangeGroupWithNonExistingGroup(): void
    {
        touch('file.txt');
        $this->assertFalse($this->ignoreError(fn () => chgrp('file.txt', 'non-exsiting-group')));
        $this->expectExceptionObject(new Warning('chgrp(): Unable to find gid for non-exsiting-group'));
        chgrp('file.txt', 'non-exsiting-group');
    }

    public function testChangeGroupWithNonExistingGid(): void
    {
        touch('file.txt');
        $this->assertFalse($this->ignoreError(fn () => chgrp('file.txt', 123)));
        $this->expectExceptionObject(new Warning('chgrp(): Operation not permitted'));
        chgrp('file.txt', 123);
    }

    public function testChangeGroupFailsWhenFileDoesNotExist(): void
    {
        $this->assertFalse($this->ignoreError(fn () => chgrp('file.txt', 123)));
        $this->expectExceptionObject(new Warning('chgrp(): No such file or directory'));
        chgrp('file.txt', 123);
    }

    public function testCanChangeAssetOwnerAndGroup(): void
    {
        touch('file.txt');

        /** @var File */
        $asset = $this->root->getChild('file.txt');

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addUser(2, 'user2');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::addGroup(2, 'group2', [2]);

        $this->assertSame(0, $asset->getUid(), 'Expected uid to be 0');
        $this->assertSame(0, $asset->getGid(), 'Expected gid to be 0');

        $this->assertTrue(chown('file.txt', 'user1'));
        $this->assertSame(1, $asset->getUid(), 'Expected uid to be 1');

        $this->assertTrue(chown('file.txt', 2));
        $this->assertSame(2, $asset->getUid(), 'Expected uid to be 2');

        $this->assertTrue(chgrp('file.txt', 'group1'));
        $this->assertSame(1, $asset->getGid(), 'Expected gid to be 1');

        $this->assertTrue(chgrp('file.txt', 2));
        $this->assertSame(2, $asset->getGid(), 'Expected gid to be 2');
    }

    public function testThrowsExceptionWhenAddingExistingUser(): void
    {
        StreamWrapper::addUser(1, 'name');
        $this->expectExceptionObject(new DuplicateUserException(1));
        StreamWrapper::addUser(1, 'name');
    }

    public function testThrowsExceptionWhenAddingExistingGroup(): void
    {
        StreamWrapper::addGroup(1, 'name');
        $this->expectExceptionObject(new DuplicateGroupException(1));
        StreamWrapper::addGroup(1, 'name');
    }

    public function testOpeningDirectoryWithoutPermission(): void
    {
        mkdir('root', 0770);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => opendir('root')));

        $this->expectExceptionObject(new Warning('opendir(tfs://root): failed to open dir: Permission denied'));
        opendir('root');
    }

    public function testOpeningDirectoryInProtectedDirectoryFails(): void
    {
        mkdir('root/dir', 0770, true);
        chmod('root/dir', 0777);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => opendir('root/dir')));

        $this->expectExceptionObject(new Warning('opendir(tfs://root/dir): failed to open dir: Permission denied'));
        opendir('root/dir');
    }

    public function testMkdirFailsWhenCreatingADirectoryInANonWritableDirectory(): void
    {
        mkdir('root', 0770);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        $this->assertFalse($this->ignoreError(fn () => mkdir('root/dir')));

        $this->expectExceptionObject(new Warning('mkdir(): Permission denied'));
        mkdir('root/dir');
    }

    public function testCanNotReadFromFileOpenedInWriteMode(): void
    {
        $fp = fopen('file.txt', 'w');
        $this->assertIsResource($fp);
        fwrite($fp, 'this is some content');
        fseek($fp, 0);
        $this->assertFalse(fgets($fp), 'Expected fgets to return false');
    }

    public function testCanNotWriteToFileOpenedInReadOnlyMode(): void
    {
        file_put_contents('file.txt', 'this is some content');
        $fp = fopen('file.txt', 'r');
        $this->assertIsResource($fp);
        $this->assertSame(0, fwrite($fp, 'new content'), 'Expected fwrite to return 0');
    }

    public function testOpenAndCreatingFileInUnwritableDirectoryFails(): void
    {
        mkdir('dir', 0770);

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);


        $this->assertFalse($this->ignoreError(fn () => fopen('dir/file', 'w+')), 'Expected fopen to fail');

        $this->expectExceptionObject(new Warning('fopen(dir/file): failed to open stream: Permission denied'));
        fopen('dir/file', 'w+');
    }

    public function testOpeningUnreadableFileFails(): void
    {
        mkdir('dir');

        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::setUid(1);
        StreamWrapper::setGid(1);

        touch('dir/file');
        chmod('dir/file', 0000);

        $this->assertFalse($this->ignoreError(fn () => fopen('dir/file', 'r')), 'Expected fopen to fail');

        $this->expectExceptionObject(new Warning('fopen(dir/file): failed to open stream: Permission denied'));
        fopen('dir/file', 'r');
    }

    public function testCanChangeGroupToGroupWhichUserIsAMember(): void
    {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::addGroup(2, 'group2', [1]);

        StreamWrapper::setUid(1);

        $this->assertTrue(touch('file.txt'), 'Expected touch to succeed');
        $this->assertTrue(chgrp('file.txt', 'group1'), 'Expected chgrp to succeed');
        $this->assertTrue(chgrp('file.txt', 'group2'), 'Expected chgrp to succeed');
    }

    public function testCanNotChangeToGroupWhenUserIsNotAMember(): void
    {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addUser(2, 'user2');
        StreamWrapper::addGroup(1, 'group1', [1]);
        StreamWrapper::addGroup(2, 'group2', [2]);

        StreamWrapper::setUid(1);

        $this->assertTrue(touch('file.txt'), 'Expected touch to succeed');
        $this->assertTrue(chgrp('file.txt', 'group1'), 'Expected chgrp to succeed');
        $this->assertFalse($this->ignoreError(fn () => chgrp('file.txt', 'group2')), 'Expected chgrp to fail');

        $this->expectExceptionObject(new Warning('chgrp(): Operation not permitted'));
        chgrp('file.txt', 'group2');
    }

    public function testCanNotChangeToGroupWhenGroupIsEmpty(): void
    {
        StreamWrapper::addUser(1, 'user1');
        StreamWrapper::addGroup(1, 'group1');
        StreamWrapper::setUid(1);

        $this->assertTrue(touch('file.txt'), 'Expected touch to succeed');

        $this->expectExceptionObject(new Warning('chgrp(): Operation not permitted'));
        chgrp('file.txt', 'group1');
    }

    public function testReturnsFalseOnMissingDirectoryIteratorWhenReading(): void
    {
        $this->assertFalse((new StreamWrapper())->dir_readdir());
    }

    public function testThrowsExceptionOnMissingDirectoryIteratorWhenRewinding(): void
    {
        $this->assertFalse((new StreamWrapper())->dir_rewinddir());
    }

    public function testThrowsExceptionOnMissingFileHandleWhenCheckingEof(): void
    {
        $this->assertTrue((new StreamWrapper())->stream_eof());
    }

    public function testThrowsExceptionOnMissingFileHandleWhenLockingStream(): void
    {
        $this->assertFalse((new StreamWrapper())->stream_lock(LOCK_EX));
    }

    public function testThrowsExceptionOnMissingFileHandleWhenReadingStream(): void
    {
        $this->assertFalse((new StreamWrapper())->stream_read(10));
    }

    public function testThrowsExceptionOnMissingFileHandleWhenSeekingInStream(): void
    {
        $this->assertFalse((new StreamWrapper())->stream_seek(10));
    }

    public function testThrowsExceptionOnMissingFileHandleWhenDoingStreamStat(): void
    {
        $this->assertFalse((new StreamWrapper())->stream_stat());
    }

    public function testThrowsExceptionOnMissingFileHandleWhenFetchingOffset(): void
    {
        $this->assertSame(0, (new StreamWrapper())->stream_tell());
    }

    public function testThrowsExceptionOnMissingFileHandleWhenTruncatingStream(): void
    {
        $this->assertFalse((new StreamWrapper())->stream_truncate(10));
    }

    public function testThrowsExceptionOnMissingFileHandleWhenWritingToStream(): void
    {
        $this->assertSame(0, (new StreamWrapper())->stream_write('some data'));
    }

    /**
     * @return array<array{url:string,expectedPath:string}>
     */
    public static function getUrls(): array
    {
        return [
            [
                'url' => 'tfs://foo',
                'expectedPath' => 'foo',
            ],
            [
                'url' => 'tfs://foo//bar\\baz',
                'expectedPath' => 'foo/bar/baz',
            ],
        ];
    }

    /**
     * @return array<string,array{path:string,expectedUrl:string}>
     */
    public static function getPaths(): array
    {
        return [
            'relative' => [
                'path' => 'foo',
                'expectedUrl' => 'tfs://foo',
            ],
            'absolute' => [
                'path' => '/foo/bar/baz.txt',
                'expectedUrl' => 'tfs://foo/bar/baz.txt',
            ],
        ];
    }
}
