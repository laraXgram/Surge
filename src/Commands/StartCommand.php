<?php

namespace LaraGram\Surge\Commands;

use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Command\SignalableCommandInterface;

#[AsCommand(name: 'surge:start')]
class StartCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'surge:start
                    {--server= : The server that should be used to serve the application}
                    {--host= : The IP address the server should bind to}
                    {--port= : The port the server should be available on [default: "9000"]}
                    {--rpc-host= : The RPC IP address the server should bind to}
                    {--rpc-port= : The RPC port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--task-workers=auto : The number of task workers that should be available to handle tasks}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}
                    {--poll : Use file system polling while watching in order to watch files over a network}
                    {--log-level= : Log messages at or above the specified log level}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Surge server';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: config('surge.server');

        return match ($server) {
            'swoole' => $this->startSwooleServer(),
            default => $this->invalidServer($server),
        };
    }

    /**
     * Start the Swoole server for Surge.
     *
     * @return int
     */
    protected function startSwooleServer()
    {
        return $this->call('surge:swoole', [
            '--host' => $this->getHost(),
            '--port' => $this->getPort(),
            '--workers' => $this->option('workers') ?: config('surge.workers', 'auto'),
            '--task-workers' => $this->option('task-workers') ?: config('surge.task_workers', 'auto'),
            '--max-requests' => $this->option('max-requests') ?: config('surge.max_requests', 500),
            '--watch' => $this->option('watch'),
            '--poll' => $this->option('poll'),
        ]);
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

    /**
     * Stop the server.
     *
     * @return void
     */
    protected function stopServer()
    {
        $server = $this->option('server') ?: config('surge.server');

        $this->callSilent('surge:stop', [
            '--server' => $server,
        ]);
    }
}
