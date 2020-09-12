<?php declare(strict_types=1);
namespace TestFs;

class FopenMode {
    private bool $read;
    private bool $write;
    private int $offset;
    private bool $truncate;
    private bool $create;
    private bool $binary;
    private bool $text;

    /**
     * Class constructor
     *
     * @param string $mode Open mode
     * @param bool $extended Extended mode
     * @param ?string $extra Extra options
     */
    public function __construct(string $mode, bool $extended, ?string $extra) {
        $this->read     = 'r' === $mode || $extended;
        $this->write    = in_array($mode, ['w', 'a', 'x', 'c']) || $extended;
        $this->offset   = 'a' === $mode ? SEEK_END : 0;
        $this->truncate = 'w' === $mode;
        $this->create   = in_array($mode, ['w', 'a', 'x', 'c']);
        $this->binary   = 'b' === $extra;
        $this->text     = 't' === $extra;
    }

    public function read() : bool {
        return $this->read;
    }

    public function write() : bool {
        return $this->write;
    }

    public function create() : bool {
        return $this->create;
    }

    public function truncate() : bool {
        return $this->truncate;
    }

    public function offset() : int {
        return $this->offset;
    }

    public function binary() : bool {
        return $this->binary;
    }

    public function text() : bool {
        return $this->text;
    }
}
