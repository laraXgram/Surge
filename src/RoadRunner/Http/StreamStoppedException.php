<?php

namespace LaraGram\Surge\RoadRunner\Http;

use LaraGram\Surge\RoadRunner\Worker\RoadRunnerException;

/**
 * Thrown into a response generator when the client stopped the stream.
 */
class StreamStoppedException extends RoadRunnerException
{
    //
}
