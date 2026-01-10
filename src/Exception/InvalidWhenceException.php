<?php declare(strict_types=1);

namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

use function sprintf;

class InvalidWhenceException extends SplInvalidArgumentException
{
    public function __construct(int $whence)
    {
        parent::__construct(sprintf('Invalid whence value: %d', $whence));
    }
}
