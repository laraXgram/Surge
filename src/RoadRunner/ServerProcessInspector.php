<?php

namespace LaraGram\Surge\RoadRunner;

use LaraGram\Surge\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use LaraGram\Surge\PosixExtension;
use LaraGram\Surge\RoadRunner\Concerns\FindsRoadRunnerBinary;
use LaraGram\Surge\ProcessFactory;
use RuntimeException;
use LaraGram\Console\Process\Process;

class ServerProcessInspector implements ServerProcessInspectorContract
{
    use FindsRoadRunnerBinary;

    public function __construct(
        protected ServerStateFile $serverStateFile,
        protected ProcessFactory $processFactory,
        protected PosixExtension $posix
    ) {
    }

    /**
     * Determine if the RoadRunner server process is running.
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return $masterProcessId && $this->posix->kill($masterProcessId, 0);
    }

    /**
     * Reload the RoadRunner workers.
     */
    public function reloadServer(): void
    {
        [
            'state' => [
                'host' => $host,
                'rpcPort' => $rpcPort,
            ],
        ] = $this->serverStateFile->read();

        tap($this->processFactory->createProcess([
            $this->findRoadRunnerBinary(),
            'reset',
            '-o', 'version=3',
            '-o', "rpc.listen=tcp://$host:$rpcPort",
            '-s',
        ], base_path()))->start()->waitUntil(function ($type, $buffer) {
            if ($type === Process::ERR) {
                throw new RuntimeException('Cannot reload RoadRunner: '.$buffer);
            }

            return true;
        });
    }

    /**
     * Stop the RoadRunner server.
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return (bool) $this->posix->kill($masterProcessId, SIGTERM);
    }
}
