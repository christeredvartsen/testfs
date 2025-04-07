<?php declare(strict_types=1);
namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;
use TestFs\Asset;

class UnknownAssetException extends SplInvalidArgumentException
{
    public function __construct(Asset|string $asset)
    {
        if ($asset instanceof Asset) {
            $asset = $asset->getName();
        }

        parent::__construct(sprintf('Child asset "%s" does not exist', $asset));
    }
}
