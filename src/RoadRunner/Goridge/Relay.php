<?php

namespace LaraGram\Surge\RoadRunner\Goridge;

use LaraGram\Surge\RoadRunner\Goridge\Exceptions\RelayFactoryException;

abstract class Relay implements RelayInterface
{
    public const TCP_SOCKET = 'tcp';

    public const UNIX_SOCKET = 'unix';

    public const PIPES = 'pipes';

    protected const CONNECTION_EXP = '/(?P<protocol>[^:\/]+):\/\/(?P<arg1>[^:]+)(:(?P<arg2>[^:]+))?/';

    /**
     * Create a relay for the given connection, e.g. "pipes", "tcp://127.0.0.1:6001" or "unix:///tmp/rr.sock".
     *
     * @throws \LaraGram\Surge\RoadRunner\Goridge\Exceptions\RelayFactoryException
     */
    public static function create(string $connection): RelayInterface
    {
        if ($connection === self::PIPES) {
            return new StreamRelay(STDIN, STDOUT);
        }

        if (! preg_match(self::CONNECTION_EXP, $connection, $match)) {
            throw new RelayFactoryException('Unsupported connection format.');
        }

        $protocol = strtolower($match['protocol']);

        return match ($protocol) {
            self::TCP_SOCKET, self::UNIX_SOCKET => new SocketRelay(
                $match['arg1'],
                isset($match['arg2']) ? (int) $match['arg2'] : null,
                $protocol === self::TCP_SOCKET ? SocketType::TCP : SocketType::UNIX,
            ),
            self::PIPES => isset($match['arg2'])
                ? new StreamRelay(static::openStream($match['arg1'], 'rb'), static::openStream($match['arg2'], 'wb'))
                : throw new RelayFactoryException('Unsupported stream connection format.'),
            default => throw new RelayFactoryException('Unknown connection protocol.'),
        };
    }

    /**
     * Determine if a frame can be read without blocking.
     */
    public function hasFrame(): bool
    {
        return false;
    }

    /**
     * Open the given PHP stream.
     *
     * @return resource
     */
    protected static function openStream(string $name, string $mode)
    {
        $resource = @fopen("php://{$name}", $mode);

        if ($resource === false) {
            throw new RelayFactoryException("Unable to open the [{$name}] stream.");
        }

        return $resource;
    }
}
