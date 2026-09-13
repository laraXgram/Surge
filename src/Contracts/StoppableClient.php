<?php

namespace LaraGram\Surge\Contracts;

interface StoppableClient extends Client
{
    /**
     * Stop the underlying server / worker.
     */
    public function stop(): void;
}
