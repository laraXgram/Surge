<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Support\NamespacedItemResolver;

class FlushTranslatorCache
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        if (! $event->sandbox->resolved('translator')) {
            return;
        }

        $translator = $event->sandbox->make('translator');

        if ($translator instanceof NamespacedItemResolver) {
            $translator->flushParsedKeys();
        }
    }
}
