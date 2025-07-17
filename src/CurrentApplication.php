<?php

namespace LaraGram\Surge;

use LaraGram\Container\Container;
use LaraGram\Foundation\Application;
use LaraGram\Support\Facades\Facade;

class CurrentApplication
{
    /**
     * Set the current application in the container.
     */
    public static function set(Application $app): void
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);

        Container::setInstance($app);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }
}
