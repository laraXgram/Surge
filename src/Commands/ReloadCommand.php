<?php

namespace LaraGram\Surge\Commands;

use LaraGram\Surge\FrankenPhp\ServerProcessInspector as FrankenPhpServerProcessInspector;
use LaraGram\Surge\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use LaraGram\Surge\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use LaraGram\Console\Attribute\AsCommand;

#[AsCommand(name: 'surge:reload')]
class ReloadCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'surge:reload {--server= : The server that is running the application}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Reload the Surge workers';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: config('surge.server');

        return match ($server) {
            'swoole' => $this->reloadSwooleServer(),
            'roadrunner' => $this->reloadRoadRunnerServer(),
            'frankenphp' => $this->reloadFrankenPhpServer(),
            default => $this->invalidServer($server),
        };
    }

    /**
     * Reload the Swoole server for Surge.
     *
     * @return int
     */
    protected function reloadSwooleServer()
    {
        $inspector = app(SwooleServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            $this->components->error('Surge server is not running.');

            return 1;
        }

        $this->components->info('Reloading workers...');

        $inspector->reloadServer();

        return 0;
    }

    /**
     * Reload the RoadRunner server for Surge.
     *
     * @return int
     */
    protected function reloadRoadRunnerServer()
    {
        $inspector = app(RoadRunnerServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            $this->components->error('Surge server is not running.');

            return 1;
        }

        $this->components->info('Reloading workers...');

        $inspector->reloadServer();

        return 0;
    }

    /**
     * Reload the FrankenPHP server for Surge.
     *
     * @return int
     */
    protected function reloadFrankenPhpServer()
    {
        $inspector = app(FrankenPhpServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            $this->components->error('Surge server is not running.');

            return 1;
        }

        $this->components->info('Reloading workers...');

        $inspector->reloadServer();

        return 0;
    }

    /**
     * Inform the user that the server type is invalid.
     *
     * @return int
     */
    protected function invalidServer(string $server)
    {
        $this->components->error("Invalid server: {$server}.");

        return 1;
    }
}
