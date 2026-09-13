<?php

namespace LaraGram\Surge\Listeners;

class EnsureUploadedFilesAreValid
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! function_exists('\\LaraGram\\Http\\Files\\File\\is_uploaded_file')) {
            require __DIR__.'/../../fixes/fix-file-validation.php';
        }
    }
}
