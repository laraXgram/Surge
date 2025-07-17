<?php

namespace LaraGram\Surge\Swoole;

use LaraGram\Surge\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use LaraGram\Surge\Exec;

class ServerProcessInspector implements ServerProcessInspectorContract
{
    public function __construct(
        protected SignalDispatcher $dispatcher,
        protected ServerStateFile $serverStateFile,
        protected Exec $exec,
    ) {
    }

    /**
     * Determine if the Swoole server process is running.
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
            'managerProcessId' => $managerProcessId
        ] = $this->serverStateFile->read();

        return $managerProcessId
                ? $masterProcessId && $managerProcessId && $this->dispatcher->canCommunicateWith((int) $managerProcessId)
                : $masterProcessId && $this->dispatcher->canCommunicateWith((int) $masterProcessId);
    }

    /**
     * Reload the Swoole workers.
     */
    public function reloadServer(): void
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        $this->dispatcher->signal((int) $masterProcessId, SIGUSR1);
    }

    /**
     * Stop the Swoole server.
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
            'managerProcessId' => $managerProcessId
        ] = $this->serverStateFile->read();

        $workerProcessIds = $this->exec->run('pgrep -P '.$managerProcessId);

        foreach ([$masterProcessId, $managerProcessId, ...$workerProcessIds] as $processId) {
            $this->dispatcher->signal((int) $processId, SIGKILL);
        }

        return true;
    }
}
