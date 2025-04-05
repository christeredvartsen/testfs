<?php declare(strict_types=1);
namespace TestFs;

use Closure;
use Exception;
use RuntimeException;

trait ErrorHandler
{
    public static function setUpBeforeClass(): void
    {
        set_error_handler(
            function (int $errno, string $errstr): void {
                if (0 !== error_reporting()) {
                    switch ($errno) {
                        case E_USER_NOTICE: throw new Notice($errstr);
                        case E_USER_WARNING: throw new Warning($errstr);
                        default: throw new RuntimeException(sprintf('unknown error: %s (%d)', $errstr, $errno));
                    }
                }
            },
        );
    }

    private function ignoreError(Closure $func): mixed
    {
        $level = error_reporting(0);
        /** @var mixed */
        $result = $func();
        error_reporting($level);
        return $result;
    }
}

class Notice extends Exception
{
    public function __construct(string $msg)
    {
        parent::__construct($msg, E_USER_NOTICE);
    }
}

class Warning extends Exception
{
    public function __construct(string $msg)
    {
        parent::__construct($msg, E_USER_WARNING);
    }
}
