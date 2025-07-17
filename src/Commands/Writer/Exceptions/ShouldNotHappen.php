<?php

declare(strict_types=1);

namespace LaraGram\Surge\Commands\Writer\Exceptions;

use RuntimeException;

/**
 * @internal
 */
final class ShouldNotHappen extends RuntimeException
{
    /**
     * @var string
     */
    private const MESSAGE = 'This should not happen';

    /**
     * Creates a new Exception instance.
     */
    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
