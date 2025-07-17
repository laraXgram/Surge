<?php

namespace LaraGram\Surge\Listeners;

class FlushTemporaryContainerInstances
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (method_exists($event->app, 'resetScope')) {
            $event->app->resetScope();
        }

        if (method_exists($event->app, 'forgetScopedInstances')) {
            $event->app->forgetScopedInstances();
        }

        foreach ($event->sandbox->make('config')->get('surge.flush', []) as $binding) {
            $event->app->forgetInstance($binding);
        }
    }
}
