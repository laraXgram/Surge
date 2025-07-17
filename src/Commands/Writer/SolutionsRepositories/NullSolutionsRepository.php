<?php

declare(strict_types=1);

namespace LaraGram\Surge\Commands\Writer\SolutionsRepositories;

use LaraGram\Surge\Commands\Writer\Contracts\SolutionsRepository;
use Throwable;

/**
 * @internal
 */
final class NullSolutionsRepository implements SolutionsRepository
{
    /**
     * {@inheritdoc}
     */
    public function getFromThrowable(Throwable $throwable): array  // @phpstan-ignore-line
    {
        return [];
    }
}
