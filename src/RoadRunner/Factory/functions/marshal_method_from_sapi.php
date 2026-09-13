<?php

declare(strict_types=1);

namespace LaraGram\Surge\RoadRunner\Factory;

/**
 * Retrieve the request method from the SAPI parameters.
 */
function marshalMethodFromSapi(array $server): string
{
    return $server['REQUEST_METHOD'] ?? 'GET';
}
