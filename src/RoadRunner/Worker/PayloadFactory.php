<?php

namespace LaraGram\Surge\RoadRunner\Worker;

use JsonException;
use LaraGram\Surge\RoadRunner\Goridge\Frame;
use LaraGram\Surge\RoadRunner\Worker\Messages\GetProcessId;
use LaraGram\Surge\RoadRunner\Worker\Messages\Pong;
use LaraGram\Surge\RoadRunner\Worker\Messages\StreamStop;
use LaraGram\Surge\RoadRunner\Worker\Messages\WorkerStop;

final class PayloadFactory
{
    /**
     * Create the payload carried by the given frame.
     */
    public static function fromFrame(Frame $frame): Payload
    {
        $payload = $frame->payload ?? '';

        if ($frame->hasFlag(Frame::CONTROL)) {
            return self::makeControl($payload);
        }

        if (($frame->byte10 & Frame::BYTE10_STOP) !== 0) {
            return new StreamStop($payload);
        }

        if (($frame->byte10 & Frame::BYTE10_PONG) !== 0) {
            return new Pong($payload);
        }

        return new Payload(
            substr($payload, $frame->options[0]),
            substr($payload, 0, $frame->options[0]),
        );
    }

    /**
     * Create the control message of the given header.
     */
    private static function makeControl(string $header): Payload
    {
        try {
            $command = json_decode($header, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RoadRunnerException('Invalid task header, JSON payload is expected: '.$e->getMessage());
        }

        if (! is_array($command)) {
            throw new RoadRunnerException('Invalid task header, JSON payload is expected.');
        }

        return match (true) {
            ! empty($command['stop']) => new WorkerStop(null, $header),
            ! empty($command['pid']) => new GetProcessId(null, $header),
            default => throw new RoadRunnerException('Invalid task header, undefined control package.'),
        };
    }
}
