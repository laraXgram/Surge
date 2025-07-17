<?php

namespace LaraGram\Surge\Listeners;

class DisconnectFromDatabases
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        foreach ($event->sandbox->make('db')->getConnections() as $connection) {
            $connection->disconnect();
        }
    }
}
