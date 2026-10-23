<?php

namespace LaraGram\Surge\RoadRunner\Worker;

/**
 * A payload exchanged with the RoadRunner server.
 */
class Payload
{
    /**
     * The binary body of the payload.
     */
    public readonly string $body;

    /**
     * The binary header (context) of the payload.
     */
    public readonly string $header;

    /**
     * Create a new payload instance.
     */
    public function __construct(?string $body, ?string $header = null, public readonly bool $eos = true)
    {
        $this->body = $body ?? '';
        $this->header = $header ?? '';
    }
}
