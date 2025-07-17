<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Tempora\LaraGram\ServiceProvider as LaraGramServiceProvider;

class FlushLocaleState
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $config = $event->sandbox->make('config');

        tap($event->sandbox->make('translator'), function ($translator) use ($config) {
            $translator->setLocale($config->get('app.locale'));
            $translator->setFallback($config->get('app.fallback_locale'));
        });

        $provider = tap(new LaraGramServiceProvider($event->app))->updateLocale();

        collect($event->sandbox->getProviders($provider))
            ->values()
            ->whenNotEmpty(fn ($providers) => $providers->first()->setAppGetter(fn () => $event->sandbox));
    }
}
