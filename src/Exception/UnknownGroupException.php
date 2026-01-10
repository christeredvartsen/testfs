<?php declare(strict_types=1);

namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

use function sprintf;

class UnknownGroupException extends SplInvalidArgumentException
{
    public function __construct(int $gid)
    {
        parent::__construct(sprintf('GID %d does not exist', $gid));
    }
}
