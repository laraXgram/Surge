<?php

namespace LaraGram\Surge\Listeners;

class EnsureUploadedFilesCanBeMoved
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! function_exists('\\LaraGram\\Http\\Files\\File\\move_uploaded_file')) {
            require __DIR__.'/../../fixes/fix-file-moving.php';
        }
    }
}
