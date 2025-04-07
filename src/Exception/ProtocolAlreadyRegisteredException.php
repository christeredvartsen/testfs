<?php declare(strict_types=1);
namespace TestFs\Exception;

class ProtocolAlreadyRegisteredException extends RuntimeException
{
    public function __construct(string $protocol)
    {
        parent::__construct(sprintf('Protocol "%s" is already registered', $protocol));
    }
}
