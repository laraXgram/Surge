<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Http\Request as HttpRequest;

class EnforceRequestScheme
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->make('config')->get('surge.https')) {
            return;
        }

        $event->sandbox->make('url')->forceScheme('https');

        if ($event->request instanceof HttpRequest) {
            $event->request->server->set('HTTPS', 'on');
        }
    }
}
