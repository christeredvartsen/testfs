<?php declare(strict_types=1);
namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

class UnknownUserException extends SplInvalidArgumentException
{
    public function __construct(int $uid)
    {
        parent::__construct(sprintf('UID %d does not exist', $uid));
    }
}
