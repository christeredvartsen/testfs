<?php declare(strict_types=1);
namespace TestFs\Exception;

use RuntimeException as SplRuntimeException;

class NoSpaceLeftOnDeviceException extends SplRuntimeException {}
