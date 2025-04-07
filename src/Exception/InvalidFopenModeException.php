<?php declare(strict_types=1);
namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

class InvalidFopenModeException extends SplInvalidArgumentException
{
    public function __construct(string $mode)
    {
        parent::__construct(sprintf('Unsupported mode: "%s"', $mode));
    }
}
