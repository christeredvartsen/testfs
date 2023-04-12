<?php declare(strict_types=1);
namespace TestFs;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass TestFs\FopenMode
 */
class FopenModeTest extends TestCase {
    /**
     * @covers ::__construct
     * @covers ::read
     * @covers ::write
     * @covers ::create
     * @covers ::truncate
     * @covers ::offset
     * @covers ::binary
     * @covers ::text
     * @dataProvider getModeData
     */
    public function testCorrectlyCreatesInstance(string $mode, bool $extended, ?string $extra, bool $expectedRead, bool $expectedWrite, int $expectedOffset, bool $expectedTruncate, bool $expectedCreate, bool $expectedBinary, bool $expectedText) : void {
        $mode = new FopenMode($mode, $extended, $extra);

        $this->assertSame($expectedRead, $mode->read(), 'Incorrect read value');
        $this->assertSame($expectedWrite, $mode->write(), 'Incorrect write value');
        $this->assertSame($expectedCreate, $mode->create(), 'Incorrect create value');
        $this->assertSame($expectedTruncate, $mode->truncate(), 'Incorrect truncate value');
        $this->assertSame($expectedOffset, $mode->offset(), 'Incorrect offset value');
        $this->assertSame($expectedBinary, $mode->binary(), 'Incorrect binary value');
        $this->assertSame($expectedText, $mode->text(), 'Incorrect text value');
    }

    /**
     * @return array<string,array{mode:string,extended:bool,extra:?string,expectedRead:bool,expectedWrite:bool,expectedOffset:int,expectedTruncate:bool,expectedCreate:bool,expectedBinary:bool,expectedText:bool}>
     */
    public static function getModeData() : array {
        return [
            'read' => [
                'mode'             => 'r',
                'extended'         => true,
                'extra'            => null,
                'expectedRead'     => true,
                'expectedWrite'    => true,
                'expectedOffset'   => 0,
                'expectedTruncate' => false,
                'expectedCreate'   => false,
                'expectedBinary'   => false,
                'expectedText'     => false
            ],
            'write' => [
                'mode'             => 'w',
                'extended'         => false,
                'extra'            => null,
                'expectedRead'     => false,
                'expectedWrite'    => true,
                'expectedOffset'   => 0,
                'expectedTruncate' => true,
                'expectedCreate'   => true,
                'expectedBinary'   => false,
                'expectedText'     => false
            ],
        ];
    }
}
