<?php

namespace LaraGram\Surge\RoadRunner\Goridge;

use LaraGram\Surge\RoadRunner\Goridge\Exceptions\HeaderException;
use LaraGram\Surge\RoadRunner\Goridge\Exceptions\InvalidArgumentException;
use LaraGram\Surge\RoadRunner\Goridge\Exceptions\TransportException;

/**
 * Talks to the server over a pair of streams, usually STDIN and STDOUT.
 */
class StreamRelay extends Relay implements BlockingRelayInterface
{
    /**
     * Create a new stream relay instance.
     *
     * @param  resource  $in
     * @param  resource  $out
     */
    public function __construct(public $in, protected $out)
    {
        if (! is_resource($in) || get_resource_type($in) !== 'stream') {
            throw new InvalidArgumentException('Expected a valid input resource stream.');
        }

        if (! in_array(stream_get_meta_data($in)['mode'], ['r', 'rb', 'r+', 'rb+', 'w+', 'wb+', 'w+b', 'a+', 'ab+', 'x+', 'c+', 'cb+'], true)) {
            throw new InvalidArgumentException('Input resource stream must be readable.');
        }

        if (! is_resource($out) || get_resource_type($out) !== 'stream') {
            throw new InvalidArgumentException('Expected a valid output resource stream.');
        }

        if (in_array(stream_get_meta_data($out)['mode'], ['r', 'rb'], true)) {
            throw new InvalidArgumentException('Output resource stream must be writable.');
        }
    }

    /**
     * Wait for the next frame from the server.
     */
    public function waitFrame(): Frame
    {
        error_clear_last();

        $header = @fread($this->in, 12);

        if ($header === false) {
            throw new HeaderException('Unable to read frame header: '.$this->lastErrorMessage());
        }

        if (strlen($header) !== 12) {
            throw new HeaderException('Unable to read frame header: Incorrect header size');
        }

        $parts = Frame::readHeader($header);

        $payload = '';
        $length = $parts[1] * 4 + $parts[2];

        while ($length > 0) {
            error_clear_last();

            $buffer = @fread($this->in, $length);

            if ($buffer === false) {
                throw new TransportException('An error occurred while reading payload from the stream: '.$this->lastErrorMessage());
            }

            $payload .= $buffer;
            $length -= strlen($buffer);
        }

        return Frame::initFrame($parts, $payload);
    }

    /**
     * Send the given frame to the server.
     */
    public function send(Frame $frame): void
    {
        $body = Frame::packFrame($frame);

        error_clear_last();

        if (@fwrite($this->out, $body, strlen($body)) === false) {
            throw new TransportException('An error occurred while write payload to the stream: '.$this->lastErrorMessage());
        }
    }

    /**
     * Determine if a frame can be read without blocking.
     */
    public function hasFrame(): bool
    {
        $read = [$this->in];
        $write = $except = null;

        return stream_select($read, $write, $except, 0) > 0;
    }

    /**
     * Get the message of the last PHP error.
     */
    protected function lastErrorMessage(): string
    {
        return error_get_last()['message'] ?? 'Unknown Error';
    }
}
