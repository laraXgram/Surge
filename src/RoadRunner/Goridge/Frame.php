<?php

namespace LaraGram\Surge\RoadRunner\Goridge;

use LaraGram\Surge\RoadRunner\Goridge\Exceptions\InvalidArgumentException;

/**
 * A frame of the Goridge protocol used to talk to the RoadRunner server.
 */
final class Frame
{
    public const VERSION = 0x01;

    public const CONTROL = 0x01;

    public const ERROR = 0x40;

    public const CODEC_RAW = 0x04;

    public const CODEC_JSON = 0x08;

    public const CODEC_MSGPACK = 0x10;

    public const CODEC_GOB = 0x20;

    public const CODEC_PROTO = 0x80;

    public const BYTE10_STREAM = 0x01;

    public const BYTE10_STOP = 0x02;

    public const BYTE10_PING = 0x04;

    public const BYTE10_PONG = 0x08;

    /**
     * The stream flags of the frame.
     */
    public int $byte10 = 0;

    /**
     * The reserved header byte.
     */
    public int $byte11 = 0;

    /**
     * Create a new frame instance.
     *
     * @param  array<int>  $options
     */
    public function __construct(
        public ?string $payload,
        public array $options = [],
        public int $flags = 0,
    ) {
    }

    /**
     * Pack the given frame into its binary representation.
     */
    public static function packFrame(Frame $frame): string
    {
        $header = pack(
            'CCL',
            self::VERSION << 4 | (count($frame->options) + 3),
            $frame->flags,
            strlen((string) $frame->payload),
        );

        if ($frame->options !== []) {
            $header .= pack('LCCL*', crc32($header), $frame->byte10, $frame->byte11, ...$frame->options);
        } else {
            $header .= pack('LCC', crc32($header), $frame->byte10, $frame->byte11);
        }

        return $header.$frame->payload;
    }

    /**
     * Parse the given 12 byte frame header.
     *
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int} The flags, options count, payload length, byte10 and byte11.
     */
    public static function readHeader(string $header): array
    {
        return [
            ord($header[1]),
            (ord($header[0]) & 0x0F) - 3,
            ord($header[2]) | ord($header[3]) << 8 | ord($header[4]) << 16 | ord($header[5]) << 24,
            ord($header[10]),
            ord($header[11]),
        ];
    }

    /**
     * Create a frame from the given parsed header and body.
     */
    public static function initFrame(array $header, string $body): Frame
    {
        $unpacked = unpack('L*', substr($body, 0, $header[1] * 4));

        $frame = new self(
            substr($body, $header[1] * 4),
            $unpacked === false ? [] : array_values($unpacked),
            $header[0],
        );

        $frame->byte10 = $header[3] ?? 0;
        $frame->byte11 = $header[4] ?? 0;

        return $frame;
    }

    /**
     * Set the given flags on the frame.
     */
    public function setFlag(int ...$flags): void
    {
        foreach ($flags as $flag) {
            if ($flag > 255) {
                throw new InvalidArgumentException('Flags can be byte only.');
            }

            $this->flags |= $flag;
        }
    }

    /**
     * Determine if the frame has the given flag.
     */
    public function hasFlag(int $flag): bool
    {
        if ($flag > 255) {
            throw new InvalidArgumentException('Flags can be byte only.');
        }

        return ($this->flags & $flag) !== 0;
    }
}
