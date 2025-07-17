<?php

namespace LaraGram\Surge\Commands\Concerns;

use LaraGram\Support\Env\Exception\InvalidPathException;
use LaraGram\Support\Env\Parser\Parser;
use LaraGram\Support\Env\Store\StoreBuilder;
use LaraGram\Support\Env;

trait InteractsWithEnvironmentVariables
{
    /**
     * Forgets the current process environment variables.
     *
     * @return void
     */
    public function forgetEnvironmentVariables()
    {
        $variables = collect();

        try {
            $content = StoreBuilder::createWithNoNames()
                ->addPath(app()->environmentPath())
                ->addName(app()->environmentFile())
                ->make()
                ->read();

            foreach ((new Parser())->parse($content) as $entry) {
                $variables->push($entry->getName());
            }
        } catch (InvalidPathException $e) {
            // ..
        }

        $variables->each(fn ($name) => Env::getRepository()->clear($name));
    }
}
