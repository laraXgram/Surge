<?php

namespace LaraGram\Surge;

class SurgeResponse
{
    /**
     * @param  \LaraGram\Request\Response|\LaraGram\Http\Response  $response
     */
    public function __construct(public $response, public ?string $outputBuffer = null)
    {
    }
}
