<?php declare(strict_types=1);

namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

use function sprintf;

class DuplicateUserException extends SplInvalidArgumentException
{
    public function __construct(int $gid)
    {
        parent::__construct(sprintf('Group with gid %d already exists', $gid));
    }
}
