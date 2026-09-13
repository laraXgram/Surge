<?php

namespace LaraGram\Surge\Commands;

use LaraGram\Surge\FrankenPhp\ServerProcessInspector as FrankenPhpProcessInspector;
use LaraGram\Surge\FrankenPhp\ServerStateFile as FrankenPhpStateFile;
use LaraGram\Surge\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use LaraGram\Surge\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use LaraGram\Surge\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use LaraGram\Surge\Swoole\ServerStateFile as SwooleServerStateFile;
use LaraGram\Console\Attribute\AsCommand;

#[AsCommand(name: 'surge:stop')]
class StopCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'surge:stop {--server= : The server that is running the application}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Stop the Surge server';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: config('surge.server');

        return match ($server) {
            'swoole' => $this->stopSwooleServer(),
            'roadrunner' => $this->stopRoadRunnerServer(),
            'frankenphp' => $this->stopFrankenPhpServer(),
            default => $this->invalidServer($server),
        };
    }

    /**
     * Stop the Swoole server for Surge.
     *
     * @return int
     */
    protected function stopSwooleServer()
    {
        $inspector = app(SwooleServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            app(SwooleServerStateFile::class)->delete();

            $this->components->error('Swoole server is not running.');

            return 1;
        }

        $this->components->info('Stopping server...');

        if (! $inspector->stopServer()) {
            $this->components->error('Failed to stop Swoole server.');

            return 1;
        }

        app(SwooleServerStateFile::class)->delete();

        return 0;
    }

    /**
     * Stop the RoadRunner server for Surge.
     *
     * @return int
     */
    protected function stopRoadRunnerServer()
    {
        $inspector = app(RoadRunnerServerProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            app(RoadRunnerServerStateFile::class)->delete();

            $this->components->error('RoadRunner server is not running.');

            return 1;
        }

        $this->components->info('Stopping server...');

        $inspector->stopServer();

        app(RoadRunnerServerStateFile::class)->delete();

        return 0;
    }

    /**
     * Stop the FrankenPHP server for Surge.
     *
     * @return int
     */
    protected function stopFrankenPhpServer()
    {
        $inspector = app(FrankenPhpProcessInspector::class);

        if (! $inspector->serverIsRunning()) {
            app(FrankenPhpStateFile::class)->delete();

            $this->components->error('FrankenPHP server is not running.');

            return 1;
        }

        $this->components->info('Stopping server...');

        $inspector->stopServer();

        app(FrankenPhpStateFile::class)->delete();

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
