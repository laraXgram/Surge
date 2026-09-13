<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Contracts\Debug\ExceptionHandler;
use LaraGram\Surge\Exceptions\DdException;
use LaraGram\Surge\Stream;

class ReportException
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if ($event->exception) {
            tap($event->sandbox, function ($sandbox) use ($event) {
                if ($event->exception instanceof DdException) {
                    return;
                }

                if ($sandbox->environment('local', 'testing')) {
                    Stream::throwable($event->exception);
                }

                $sandbox[ExceptionHandler::class]->report($event->exception);
            });
        }
    }
}
