<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Log\Logger\ResettableInterface;

class FlushLogState
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('log')) {
            return;
        }

        collect($event->sandbox->make('log')->getChannels())
            ->map->getLogger()
            ->filter(function ($logger) {
                return $logger instanceof ResettableInterface;
            })->each->reset();
    }
}
