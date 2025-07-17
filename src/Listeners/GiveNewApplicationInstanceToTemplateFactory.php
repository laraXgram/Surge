<?php

namespace LaraGram\Surge\Listeners;

class GiveNewApplicationInstanceToTemplateFactory
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('template')) {
            return;
        }

        with($event->sandbox->make('template'), function ($template) use ($event) {
            $template->setContainer($event->sandbox);

            $template->share('app', $event->sandbox);
        });
    }
}
