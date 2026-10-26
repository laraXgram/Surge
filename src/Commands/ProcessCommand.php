<?php

namespace LaraGram\Surge\Commands;

use LaraGram\Console\Attribute\AsCommand;

#[AsCommand(name: 'surge:process')]
class ProcessCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'surge:process
                    {handler : The invokable handler class of the background process}
                    {--name= : The name of the background process}
                    {--coroutine : Run the handler inside a Swoole coroutine container}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Run a Surge background process';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Handle the command.
     *
     * The RoadRunner and FrankenPHP servers launch every registered background
     * process through this command (Swoole forks them itself), so the handler
     * receives the application booted for this process and no process or server.
     *
     * @return int
     */
    public function handle()
    {
        $handlerClass = $this->argument('handler');

        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title(sprintf(
                '%s %s process',
                config('app.name', 'LaraGram'),
                $this->option('name') ?: class_basename($handlerClass),
            ));
        }

        $handler = $this->laragram->make($handlerClass);

        $run = fn () => $handler($this->laragram, null, null);

        $this->option('coroutine') && function_exists('Swoole\Coroutine\run')
            ? \Swoole\Coroutine\run($run)
            : $run();

        return 0;
    }
}
