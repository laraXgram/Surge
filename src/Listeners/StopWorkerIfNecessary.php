<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Surge\Contracts\Client;
use LaraGram\Surge\Contracts\StoppableClient;

class StopWorkerIfNecessary
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $client = $event->sandbox->make(Client::class);

        if ($client instanceof StoppableClient) {
            $client->stop();
        }
    }
}
