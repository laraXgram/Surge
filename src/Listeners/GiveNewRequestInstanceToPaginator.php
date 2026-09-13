<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Pagination\PaginationState;

class GiveNewRequestInstanceToPaginator
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        PaginationState::resolveUsing($event->sandbox);
    }
}
