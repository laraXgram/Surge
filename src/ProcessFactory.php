<?php

namespace LaraGram\Surge;

use LaraGram\Console\Process\Process;

class ProcessFactory
{
    /**
     * Create a new process instance.
     *
     * @param  mixed|null  $input
     * @return \LaraGram\Console\Process\Process
     */
    public function createProcess(array $command, ?string $cwd = null, ?array $env = null, $input = null, ?float $timeout = 60)
    {
        return new Process($command, $cwd, $env, $input, $timeout);
    }
}
