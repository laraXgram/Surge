<?php

namespace LaraGram\Surge\Listeners;

class CreateUrlGeneratorSandbox
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $event->sandbox->instance('url', clone $event->sandbox['url']);
    }
}
