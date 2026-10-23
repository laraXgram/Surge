<?php

namespace LaraGram\Surge\RoadRunner\Http;

final class GlobalState
{
    /**
     * The server variables of the worker process.
     */
    private static ?array $cachedServer = null;

    /**
     * Get the $_SERVER variables of the given request.
     */
    public static function enrichServerVars(Request $request): array
    {
        $server = self::$cachedServer ??= $_SERVER;

        $server['REQUEST_URI'] = $request->uri;
        $server['REQUEST_TIME'] = time();
        $server['REQUEST_TIME_FLOAT'] = microtime(true);
        $server['REMOTE_ADDR'] = $request->getRemoteAddr();
        $server['REQUEST_METHOD'] = $request->method;
        $server['HTTP_USER_AGENT'] = '';

        $parts = parse_url($request->uri);

        if (isset($parts['host'])) {
            $server['HTTP_HOST'] = isset($parts['port']) ? $parts['host'].':'.$parts['port'] : $parts['host'];
        }

        foreach ($request->headers as $key => $value) {
            $key = strtoupper(str_replace('-', '_', (string) $key));

            $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH']) ? $key : 'HTTP_'.$key] = implode(', ', $value);
        }

        return $server;
    }
}
