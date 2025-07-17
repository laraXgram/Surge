<?php

namespace LaraGram\Surge\Commands;

use LaraGram\Surge\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use LaraGram\Console\Attribute\AsCommand;

#[AsCommand(name: 'surge:status')]
class StatusCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'surge:status {--server= : The server that is running the application}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Get the current status of the Surge server';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: config('surge.server');

        $isRunning = match ($server) {
            'swoole' => $this->isSwooleServerRunning(),
            default => $this->invalidServer($server),
        };

        return ! tap($isRunning, function ($isRunning) {
            $isRunning
                ? $this->components->info('Surge server is running.')
                : $this->components->info('Surge server is not running.');
        });
    }

    /**
     * Check if the Swoole server is running.
     *
     * @return bool
     */
    protected function isSwooleServerRunning()
    {
        return app(SwooleServerProcessInspector::class)
            ->serverIsRunning();
    }

    /**
     * Inform the user that the server type is invalid.
     *
     * @return bool
     */
    protected function invalidServer(string $server)
    {
        $this->components->error("Invalid server: {$server}.");

        return false;
    }
}
