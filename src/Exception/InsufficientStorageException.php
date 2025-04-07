<?php declare(strict_types=1);
namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

class InsufficientStorageException extends SplInvalidArgumentException
{
    public function __construct(int $available, int $required)
    {
        parent::__construct(sprintf(
            'Insufficient storage: %d byte(s) available, %d byte(s) required',
            $available,
            $required,
        ));
    }
}
