<?php

namespace LaraGram\Surge\RoadRunner\Goridge;

interface RelayInterface
{
    /**
     * Wait for the next frame from the server.
     *
     * @throws \LaraGram\Surge\RoadRunner\Goridge\Exceptions\RelayException
     */
    public function waitFrame(): Frame;

    /**
     * Send the given frame to the server.
     *
     * @throws \LaraGram\Surge\RoadRunner\Goridge\Exceptions\RelayException
     */
    public function send(Frame $frame): void;

    /**
     * Determine if a frame can be read without blocking.
     */
    public function hasFrame(): bool;
}
