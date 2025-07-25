<?php

namespace LaraGram\Surge\Listeners;

class CloseLogHandlers
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->app->resolved('log')) {
            return;
        }

        collect($event->app->make('log')->getChannels())
            ->map
            ->getLogger()
            ->filter(fn ($logger) => method_exists($logger, 'close'))
            ->each
            ->close();
    }
}
