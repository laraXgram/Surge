<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Contracts\Bot\Kernel;

class GiveNewApplicationInstanceToBotKernel
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $event->sandbox->make(Kernel::class)->setApplication($event->sandbox);
    }
}
