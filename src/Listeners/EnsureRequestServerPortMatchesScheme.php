<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Http\Request as HttpRequest;

class EnsureRequestServerPortMatchesScheme
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->request instanceof HttpRequest) {
            return;
        }

        $port = $event->request->getPort();

        if (is_null($port) || $port === '') {
            $event->request->server->set(
                'SERVER_PORT',
                $event->request->getScheme() === 'https' ? 443 : 80
            );
        }
    }
}
