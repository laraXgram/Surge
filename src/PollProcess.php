<?php

namespace LaraGram\Surge;

use LaraGram\Console\Output\ConsoleOutput;
use LaraGram\Contracts\Console\Kernel;

class PollProcess
{
    /**
     * Receive bot updates with long polling and hand them to the Surge server.
     *
     * Each update is posted to the server the way Telegram calls a webhook, so
     * workers, task workers, and the per-update sandbox handle it like any
     * other update, on every Surge server.
     *
     * @param  \LaraGram\Contracts\Foundation\Application  $app
     * @return void
     */
    public function __invoke($app)
    {
        $app->make(Kernel::class)->call('poll', [
            '--forward' => $this->serverUrl($app),
            '--concurrency' => (int) $app['config']->get('surge.polling_concurrency', 10),
        ], new ConsoleOutput);
    }

    /**
     * Get the URL of the running Surge server.
     *
     * @param  \LaraGram\Contracts\Foundation\Application  $app
     * @return string
     */
    protected function serverUrl($app)
    {
        $path = $app['config']->get('surge.state_file', $app->storagePath('logs/surge-server-state.json'));

        $state = (is_readable($path) ? json_decode(file_get_contents($path), true) : [])['state'] ?? [];

        $host = $state['host'] ?? $app['config']->get('surge.host', '127.0.0.1');

        if (in_array($host, ['', '0.0.0.0', '::', '[::]'], true)) {
            $host = '127.0.0.1';
        }

        return 'http://'.$host.':'.($state['port'] ?? $app['config']->get('surge.port', 9000));
    }
}
