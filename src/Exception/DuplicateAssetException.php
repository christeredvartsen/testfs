<?php declare(strict_types=1);
namespace TestFs\Exception;

use InvalidArgumentException as SplInvalidArgumentException;
use TestFs\Asset;
use TestFs\Directory;

class DuplicateAssetException extends SplInvalidArgumentException
{
    public function __construct(Directory|string $parent, Asset|string $child)
    {
        if ($parent instanceof Directory) {
            $parent = $parent->getName();
        }

        if ($child instanceof Asset) {
            $child = $child->getName();
        }

        parent::__construct(sprintf(
            'Directory "%s" already has a child named "%s"',
            $parent,
            $child,
        ));
    }
}
