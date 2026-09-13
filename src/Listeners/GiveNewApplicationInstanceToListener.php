<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Listening\CompiledListenCollection;
use LaraGram\Listening\ListenCollection;

class GiveNewApplicationInstanceToListener
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('listener')) {
            return;
        }

        $event->sandbox->make('listener')->setContainer($event->sandbox);

        if (! $event->sandbox->resolved('listens')) {
            return;
        }

        $listens = $event->sandbox->make('listens');

        if ($listens instanceof CompiledListenCollection) {
            $listens->setContainer($event->sandbox);

            foreach ((function () {
                return $this->nameCache ?? [];
            })->call($listens) as $listen) {
                $listen->setContainer($event->sandbox);
            }

            return;
        }

        if ($listens instanceof ListenCollection) {
            foreach ($listens as $listen) {
                $listen->setContainer($event->sandbox);
            }
        }
    }
}
