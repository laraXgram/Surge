<?php

namespace LaraGram\Surge\FrankenPhp\Concerns;

use LaraGram\Console\Process\ExecutableFinder;

trait FindsFrankenPhpBinary
{
    /**
     * Find the FrankenPHP binary used by the application.
     */
    protected function findFrankenPhpBinary(): ?string
    {
        return (new ExecutableFinder())->find('frankenphp', null, [base_path()]);
    }
}
