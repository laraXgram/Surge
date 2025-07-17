<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Support\Str;

class FlushStrCache
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        Str::flushCache();
    }
}
