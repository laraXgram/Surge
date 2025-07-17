<?php

namespace LaraGram\Surge;

use LaraGram\Support\SerializableClosure\Support\ClosureStream;
use Throwable;

class Stream
{
    /**
     * Stream the given request information to stdout.
     *
     * @return void
     */
    public static function request(string $method, string $pattern, float $duration)
    {
        fwrite(STDOUT, json_encode([
            'type' => 'request',
            'method' => $method,
            'pattern' => $pattern,
            'memory' => memory_get_usage(),
            'duration' => $duration,
        ])."\n");
    }

    /**
     * Stream the given throwable to stderr.
     *
     * @return void
     */
    public static function throwable(Throwable $throwable)
    {
        $fallbackTrace = str_starts_with($throwable->getFile(), ClosureStream::STREAM_PROTO.'://')
            ? collect($throwable->getTrace())->whereNotNull('file')->first()
            : null;

        Surge::writeError(json_encode([
            'type' => 'throwable',
            'class' => $throwable::class,
            'code' => $throwable->getCode(),
            'file' => $fallbackTrace['file'] ?? $throwable->getFile(),
            'line' => $fallbackTrace['line'] ?? (int) $throwable->getLine(),
            'message' => $throwable->getMessage(),
            'trace' => array_slice($throwable->getTrace(), 0, 2),
        ]));
    }

    /**
     * Stream the given shutdown throwable to stderr.
     *
     * @return void
     */
    public static function shutdown(Throwable $throwable)
    {
        Surge::writeError(json_encode([
            'type' => 'shutdown',
            'class' => $throwable::class,
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'message' => $throwable->getMessage(),
            'trace' => array_slice($throwable->getTrace(), 0, 2),
        ]));
    }
}
