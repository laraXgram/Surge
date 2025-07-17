<?php

namespace LaraGram\Surge\Cache;

use Closure;
use LaraGram\Cache\ArrayStore;

class SurgeArrayStore extends ArrayStore
{
    /**
     * Register a cache key that should be refreshed at a given interval (in minutes).
     *
     * @param  string  $key
     * @param  int  $seconds
     * @return mixed
     */
    public function interval($key, Closure $resolver, $seconds)
    {
        return $resolver();
    }

    /**
     * Refresh all of the applicable interval caches.
     *
     * @return void
     */
    public function refreshIntervalCaches()
    {
        //
    }
}
