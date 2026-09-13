<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Http\Request as HttpRequest;
use LaraGram\Support\Arr;
use SplFileInfo;

class FlushUploadedFiles
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->request instanceof HttpRequest) {
            return;
        }

        foreach ($event->request->files->all() as $files) {
            foreach (Arr::wrap($files) as $file) {
                if (! $file instanceof SplFileInfo ||
                    ! is_string($path = $file->getRealPath())) {
                    continue;
                }

                clearstatcache(true, $path);

                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
