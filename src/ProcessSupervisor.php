<?php

namespace LaraGram\Surge;

use LaraGram\Console\Process\PhpExecutableFinder;
use LaraGram\Console\Process\Process;

class ProcessSupervisor
{
    /**
     * The supervised processes, keyed by their position in the "processes" config.
     *
     * @var array<int, array{definition: array, process: \LaraGram\Console\Process\Process|null, startedAt: float}>
     */
    protected array $processes = [];

    /**
     * Create a new process supervisor instance.
     *
     * @param  array  $definitions
     * @param  int  $minimumLifetime
     */
    public function __construct(
        protected array $definitions,
        protected int $minimumLifetime = 5,
    ) {
    }

    /**
     * Create a supervisor for the background processes registered with Surge.
     *
     * @return static
     */
    public static function fromConfig()
    {
        return new static(config('surge.processes', []));
    }

    /**
     * Start every background process.
     *
     * @return $this
     */
    public function start()
    {
        foreach ($this->definitions as $index => $definition) {
            $this->processes[$index] = [
                'definition' => is_array($definition) ? $definition : ['handler' => $definition],
                'process' => null,
                'startedAt' => 0.0,
            ];

            $this->launch($index);
        }

        return $this;
    }

    /**
     * Forward the processes' output and restart the ones that exited.
     *
     * A process that exits within its minimum lifetime is restarted only once
     * that lifetime has passed, so a failing handler does not restart in a loop.
     *
     * @param  callable(string, bool): void  $output
     * @return void
     */
    public function supervise(callable $output)
    {
        foreach ($this->processes as $index => $entry) {
            $process = $entry['process'];

            $this->writeOutput($process, $output);

            if ($process->isRunning()) {
                continue;
            }

            if (microtime(true) - $entry['startedAt'] >= $this->minimumLifetime) {
                $this->launch($index);
            }
        }
    }

    /**
     * Stop every background process.
     *
     * @return void
     */
    public function stop()
    {
        foreach ($this->processes as $entry) {
            if ($entry['process']?->isRunning()) {
                $entry['process']->stop(5, SIGTERM);
            }
        }

        $this->processes = [];
    }

    /**
     * Launch (or relaunch) the given process.
     *
     * @param  int  $index
     * @return void
     */
    protected function launch($index)
    {
        $definition = $this->processes[$index]['definition'];

        $process = new Process(array_values(array_filter([
            (new PhpExecutableFinder)->find(),
            base_path('laragram'),
            'surge:process',
            $definition['handler'],
            isset($definition['name']) ? '--name='.$definition['name'] : null,
            ($definition['coroutine'] ?? true) ? '--coroutine' : null,
        ])), base_path(), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'LARAGRAM_SURGE' => 1,
        ], null, null);

        $process->start();

        $this->processes[$index]['process'] = $process;
        $this->processes[$index]['startedAt'] = microtime(true);
    }

    /**
     * Forward the incremental output of the given process.
     *
     * @param  \LaraGram\Console\Process\Process  $process
     * @param  callable(string, bool): void  $output
     * @return void
     */
    protected function writeOutput($process, callable $output)
    {
        foreach ([[$process->getIncrementalOutput(), false], [$process->getIncrementalErrorOutput(), true]] as [$text, $isError]) {
            foreach (array_filter(explode("\n", $text)) as $line) {
                $output($line, $isError);
            }
        }

        $process->clearOutput()->clearErrorOutput();
    }
}
