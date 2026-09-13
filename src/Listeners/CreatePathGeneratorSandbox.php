<?php

namespace LaraGram\Surge\Listeners;

class CreatePathGeneratorSandbox
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $event->sandbox->instance('listener.path', clone $event->sandbox['listener.path']);
    }
}
