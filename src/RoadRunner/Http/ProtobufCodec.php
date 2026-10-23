<?php

namespace LaraGram\Surge\RoadRunner\Http;

use LaraGram\Surge\RoadRunner\Worker\RoadRunnerException;

/**
 * Encodes and decodes the protobuf messages of the RoadRunner HTTP plugin (http.v1).
 *
 * message HeaderValue { repeated bytes value = 1; }
 * message Request {
 *     string remote_addr = 1; string protocol = 2; string method = 3; string uri = 4;
 *     map<string, HeaderValue> header = 5; map<string, HeaderValue> cookies = 6;
 *     string raw_query = 7; bool parsed = 8; bytes uploads = 9;
 *     map<string, HeaderValue> attributes = 10;
 * }
 * message Response { int64 status = 1; map<string, HeaderValue> headers = 2; }
 */
final class ProtobufCodec
{
    private const VARINT = 0;

    private const FIXED64 = 1;

    private const LENGTH_DELIMITED = 2;

    private const FIXED32 = 5;

    private const REQUEST_MAPS = [5 => 'header', 6 => 'cookies', 10 => 'attributes'];

    private const REQUEST_STRINGS = [1 => 'remote_addr', 2 => 'protocol', 3 => 'method', 4 => 'uri', 7 => 'raw_query', 9 => 'uploads'];

    /**
     * Decode the given http.v1.Request message.
     *
     * @return array{remote_addr: string, protocol: string, method: string, uri: string, raw_query: string, parsed: bool, uploads: string, header: array<string, list<string>>, cookies: array<string, list<string>>, attributes: array<string, list<string>>}
     */
    public static function decodeRequest(string $message): array
    {
        $request = array_fill_keys(self::REQUEST_STRINGS, '') + array_fill_keys(self::REQUEST_MAPS, []) + ['parsed' => false];

        foreach (self::fields($message) as [$number, $type, $value]) {
            if ($type === self::LENGTH_DELIMITED && isset(self::REQUEST_STRINGS[$number])) {
                $request[self::REQUEST_STRINGS[$number]] = $value;
            } elseif ($type === self::LENGTH_DELIMITED && isset(self::REQUEST_MAPS[$number])) {
                [$key, $values] = self::decodeMapEntry($value);

                $request[self::REQUEST_MAPS[$number]][$key] = $values;
            } elseif ($type === self::VARINT && $number === 8) {
                $request['parsed'] = $value !== 0;
            }
        }

        return $request;
    }

    /**
     * Encode the given status and headers as an http.v1.Response message.
     *
     * @param  array<array-key, string|array<array-key, string>>  $headers
     */
    public static function encodeResponse(int $status, array $headers): string
    {
        $message = self::tag(1, self::VARINT).self::varint($status);

        foreach ($headers as $name => $values) {
            $values = array_filter(is_array($values) ? $values : [$values], 'is_string');

            if ($values === []) {
                continue;
            }

            $headerValue = '';

            foreach ($values as $value) {
                $headerValue .= self::bytes(1, $value);
            }

            $message .= self::bytes(2, self::bytes(1, (string) $name).self::bytes(2, $headerValue));
        }

        return $message;
    }

    /**
     * Decode a map<string, HeaderValue> entry.
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function decodeMapEntry(string $entry): array
    {
        $key = '';
        $values = [];

        foreach (self::fields($entry) as [$number, $type, $value]) {
            if ($type !== self::LENGTH_DELIMITED) {
                continue;
            }

            if ($number === 1) {
                $key = $value;
            } elseif ($number === 2) {
                foreach (self::fields($value) as [$valueNumber, $valueType, $item]) {
                    if ($valueNumber === 1 && $valueType === self::LENGTH_DELIMITED) {
                        $values[] = $item;
                    }
                }
            }
        }

        return [$key, $values];
    }

    /**
     * Iterate over the fields of the given message.
     *
     * @return \Generator<int, array{0: int, 1: int, 2: int|string}>
     */
    private static function fields(string $message): \Generator
    {
        $offset = 0;
        $length = strlen($message);

        while ($offset < $length) {
            $key = self::readVarint($message, $offset);
            $number = $key >> 3;
            $type = $key & 0x07;

            $value = match ($type) {
                self::VARINT => self::readVarint($message, $offset),
                self::FIXED64 => self::skip($message, $offset, 8),
                self::FIXED32 => self::skip($message, $offset, 4),
                self::LENGTH_DELIMITED => self::skip($message, $offset, self::readVarint($message, $offset)),
                default => throw new RoadRunnerException("Unsupported protobuf wire type [{$type}]."),
            };

            yield [$number, $type, $value];
        }
    }

    /**
     * Read a varint at the given offset.
     */
    private static function readVarint(string $message, int &$offset): int
    {
        $result = 0;
        $shift = 0;

        do {
            if (! isset($message[$offset])) {
                throw new RoadRunnerException('Truncated protobuf message.');
            }

            $byte = ord($message[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);

        return $result;
    }

    /**
     * Read the given number of bytes at the given offset.
     */
    private static function skip(string $message, int &$offset, int $length): string
    {
        if ($offset + $length > strlen($message)) {
            throw new RoadRunnerException('Truncated protobuf message.');
        }

        $value = substr($message, $offset, $length);

        $offset += $length;

        return $value;
    }

    /**
     * Encode a length-delimited field.
     */
    private static function bytes(int $number, string $value): string
    {
        return self::tag($number, self::LENGTH_DELIMITED).self::varint(strlen($value)).$value;
    }

    /**
     * Encode a field tag.
     */
    private static function tag(int $number, int $type): string
    {
        return self::varint(($number << 3) | $type);
    }

    /**
     * Encode a varint.
     */
    private static function varint(int $value): string
    {
        $bytes = '';

        do {
            $byte = $value & 0x7F;
            $value = ($value >> 7) & (PHP_INT_MAX >> 6);

            $bytes .= chr($value !== 0 ? $byte | 0x80 : $byte);
        } while ($value !== 0);

        return $bytes;
    }
}
