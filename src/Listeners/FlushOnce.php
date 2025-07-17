<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Support\Once;

class FlushOnce
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (class_exists(Once::class)) {
            Once::flush();
        }
    }
}
