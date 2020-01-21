<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass TestFs\AssetFactory
 */
class AssetFactoryTest extends TestCase {
    /**
     * @covers ::file
     */
    public function testCanCreateFile() : void {
        $this->assertInstanceOf(File::class, (new AssetFactory)->file('name'), 'Expected file instance');
    }

    /**
     * @covers ::directory
     */
    public function testCanCreateDirectory() : void {
        $this->assertInstanceOf(Directory::class, (new AssetFactory)->directory('name'), 'Expected directory instance');
    }
}
