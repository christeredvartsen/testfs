<?php declare(strict_types=1);
namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;

class InvalidUrlException extends SplInvalidArgumentException
{
    public function __construct(string $url)
    {
        parent::__construct(sprintf('Invalid URL: "%s"', $url));
    }
}
