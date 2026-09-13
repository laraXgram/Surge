<?php

declare(strict_types=1);

namespace LaraGram\Surge\RoadRunner\Factory\Exceptions;

use UnexpectedValueException;

use function sprintf;

class UnrecognizedProtocolVersionException extends UnexpectedValueException implements ExceptionInterface
{
    public static function forVersion(string $version): self
    {
        return new self(sprintf('Unrecognized protocol version (%s)', $version));
    }
}
