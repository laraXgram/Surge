<?php

namespace LaraGram\Surge\RoadRunner\Goridge;

use LaraGram\Surge\RoadRunner\Goridge\Exceptions\HeaderException;
use LaraGram\Surge\RoadRunner\Goridge\Exceptions\InvalidArgumentException;
use LaraGram\Surge\RoadRunner\Goridge\Exceptions\RelayException;
use LaraGram\Surge\RoadRunner\Goridge\Exceptions\TransportException;
use Socket;
use Stringable;
use Throwable;

/**
 * Talks to the server over a TCP or Unix socket.
 */
class SocketRelay extends Relay implements Stringable
{
    public const RECONNECT_RETRIES = 10;

    public const RECONNECT_TIMEOUT = 100;

    /**
     * The connected socket.
     */
    public ?Socket $socket = null;

    /**
     * Create a new socket relay instance.
     */
    public function __construct(
        protected string $address,
        protected ?int $port = null,
        protected SocketType $type = SocketType::TCP,
    ) {
        if (! extension_loaded('sockets')) {
            throw new RelayException('The sockets extension is required to use a socket relay.');
        }

        if ($type === SocketType::UNIX) {
            $this->port = null;

            return;
        }

        $this->address = strtolower($address);

        if ($port === null || $port < 0 || $port > 65535) {
            throw new InvalidArgumentException("Invalid port given for TCP socket on [{$this->address}].");
        }
    }

    /**
     * Determine if the socket is connected.
     */
    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    /**
     * Wait for the next frame from the server.
     */
    public function waitFrame(): Frame
    {
        $this->connect();

        $header = '';

        if (socket_recv($this->socket, $header, 12, MSG_WAITALL) !== 12) {
            throw new HeaderException('Unable to read frame header: '.socket_strerror(socket_last_error($this->socket)));
        }

        $parts = Frame::readHeader($header);

        $payload = '';
        $length = $parts[1] * 4 + $parts[2];

        while ($length > 0) {
            $bufferLength = socket_recv($this->socket, $buffer, $length, MSG_WAITALL);

            if ($bufferLength === false || $buffer === null) {
                throw new HeaderException('Unable to read payload from socket: '.socket_strerror(socket_last_error($this->socket)));
            }

            $payload .= $buffer;
            $length -= $bufferLength;
        }

        return Frame::initFrame($parts, $payload);
    }

    /**
     * Send the given frame to the server.
     */
    public function send(Frame $frame): void
    {
        $this->connect();

        $body = Frame::packFrame($frame);

        if (socket_send($this->socket, $body, strlen($body), 0) === false) {
            throw new TransportException('Unable to write payload to the stream.');
        }
    }

    /**
     * Determine if a frame can be read without blocking.
     */
    public function hasFrame(): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        $read = [$this->socket];
        $write = $except = null;

        return socket_select($read, $write, $except, 0) > 0;
    }

    /**
     * Connect to the server, retrying for a while.
     */
    public function connect(int $retries = self::RECONNECT_RETRIES, int $timeout = self::RECONNECT_TIMEOUT): bool
    {
        if ($this->isConnected()) {
            return true;
        }

        $socket = $this->type === SocketType::UNIX
            ? socket_create(AF_UNIX, SOCK_STREAM, 0)
            : socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket === false) {
            throw new RelayException("Unable to create socket {$this}.");
        }

        try {
            $status = false;

            for ($attempt = 0; $attempt <= $retries; $attempt++) {
                if ($status = @socket_connect($socket, $this->address, $this->port ?? 0)) {
                    break;
                }

                usleep(max(0, $timeout));
            }

            if ($status === false) {
                throw new RelayException(socket_strerror(socket_last_error($socket)));
            }
        } catch (Throwable $e) {
            throw new RelayException("Unable to establish connection {$this}.", 0, $e);
        }

        $this->socket = $socket;

        return true;
    }

    /**
     * Close the socket.
     */
    public function close(): void
    {
        if (! $this->isConnected()) {
            throw new RelayException("Unable to close socket {$this}, socket already closed.");
        }

        socket_close($this->socket);

        $this->socket = null;
    }

    /**
     * Get the address of the socket.
     */
    public function __toString(): string
    {
        return $this->type === SocketType::TCP
            ? "tcp://{$this->address}:{$this->port}"
            : "unix://{$this->address}";
    }

    /**
     * Drop the socket of the cloned relay.
     */
    public function __clone()
    {
        $this->socket = null;
    }

    /**
     * Close the socket when the relay is destroyed.
     */
    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->close();
        }
    }
}
