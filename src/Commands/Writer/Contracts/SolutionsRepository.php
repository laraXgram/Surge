<?php

declare(strict_types=1);

namespace LaraGram\Surge\Commands\Writer\Contracts;

use Throwable;

/**
 * @internal
 */
interface SolutionsRepository
{
    /**
     * Gets the solutions from the given `$throwable`.
     *
     * @param Throwable $throwable
     * @return array
     */
    public function getFromThrowable(Throwable $throwable): array; // @phpstan-ignore-line
}
