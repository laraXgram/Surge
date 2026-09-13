<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Http\Request as HttpRequest;

class GiveNewRequestInstanceToApplication
{
    /**
     * The idle bot request (no update), bound while an HTTP request is handled.
     *
     * @var \LaraGram\Request\Request|null
     */
    protected $idleBotRequest = null;

    /**
     * The idle HTTP request, bound while a Telegram update is handled.
     *
     * @var \LaraGram\Http\Request|null
     */
    protected $idleHttpRequest = null;

    /**
     * Handle the event.
     *
     * LaraGram binds the Telegram update as "request" and the web request as "http.request".
     * The current request is bound under its own key, and the other key is reset to an idle
     * request, so an HTTP request never sees a previous update (e.g. through chat() or user())
     * and a Telegram update never sees a previous web request (e.g. through url() or request()).
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        // The first time, the application still holds the idle requests bound by the console bootstrapper.
        $this->idleBotRequest ??= $event->app->make('request');
        $this->idleHttpRequest ??= $event->app->make('http.request');

        [$current, $other, $idle] = $event->request instanceof HttpRequest
            ? ['http.request', 'request', $this->idleBotRequest]
            : ['request', 'http.request', $this->idleHttpRequest];

        $event->app->instance($current, $event->request);
        $event->sandbox->instance($current, $event->request);

        $event->app->instance($other, $idle);
        $event->sandbox->instance($other, $idle);
    }
}
