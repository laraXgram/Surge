<?php

namespace LaraGram\Surge\RoadRunner\Worker;

use LaraGram\Surge\RoadRunner\Goridge\BlockingRelayInterface;
use LaraGram\Surge\RoadRunner\Goridge\Exceptions\GoridgeException;
use LaraGram\Surge\RoadRunner\Goridge\Exceptions\TransportException;
use LaraGram\Surge\RoadRunner\Goridge\Frame;
use LaraGram\Surge\RoadRunner\Goridge\RelayInterface;
use LaraGram\Surge\RoadRunner\Worker\Messages\GetProcessId;
use LaraGram\Surge\RoadRunner\Worker\Messages\Pong;
use LaraGram\Surge\RoadRunner\Worker\Messages\SkipMessage;
use LaraGram\Surge\RoadRunner\Worker\Messages\WorkerStop;
use Throwable;

/**
 * Receives payloads from the RoadRunner server over a Goridge relay.
 */
class Worker
{
    /**
     * The payloads that were received ahead of time.
     *
     * @var array<int, \LaraGram\Surge\RoadRunner\Worker\Payload>
     */
    protected array $payloads = [];

    /**
     * The number of frames sent in stream mode.
     */
    protected int $framesSent = 0;

    /**
     * Indicates if the worker is streaming a response.
     */
    protected bool $streamMode = false;

    /**
     * Indicates if the next stream frame should ping the server.
     */
    protected bool $shouldPing = false;

    /**
     * Indicates if the worker is waiting for a pong.
     */
    protected bool $waitingPong = false;

    /**
     * Create a new worker instance.
     */
    public function __construct(protected RelayInterface $relay, bool $interceptSideEffects = true)
    {
        if ($interceptSideEffects) {
            StdoutHandler::register();
        }
    }

    /**
     * Wait for the next payload, or null when the worker should stop.
     */
    public function waitPayload(): ?Payload
    {
        while (true) {
            $payload = $this->payloads !== []
                ? array_shift($this->payloads)
                : PayloadFactory::fromFrame($this->relay->waitFrame());

            switch (true) {
                case $payload::class === Payload::class:
                    return $payload;
                case $payload instanceof WorkerStop:
                    $this->waitingPong = false;

                    return null;
                case $payload instanceof GetProcessId:
                    $this->sendFrame(new Frame($this->encode(['pid' => getmypid()]), [], Frame::CONTROL));

                    continue 2;
                case $payload instanceof Pong:
                    $this->waitingPong = false;

                    continue 2;
                case $payload instanceof SkipMessage:
                    continue 2;
            }
        }
    }

    /**
     * Get a copy of the worker that streams its responses.
     */
    public function withStreamMode(): static
    {
        $clone = clone $this;

        $clone->streamMode = true;
        $clone->framesSent = 0;
        $clone->shouldPing = false;
        $clone->waitingPong = false;

        return $clone;
    }

    /**
     * Send the given payload to the server, with its header encoded by the given codec.
     */
    public function respond(Payload $payload, ?int $codec = null): void
    {
        if ($this->streamMode) {
            $this->framesSent++;
        }

        $this->send($payload->body, $payload->header, $payload->eos, $codec);
    }

    /**
     * Report the given error to the server.
     */
    public function error(string $error): void
    {
        $this->sendFrame(new Frame($error, [], Frame::ERROR));
    }

    /**
     * Ask the server to stop the worker.
     */
    public function stop(): void
    {
        $this->send('', $this->encode(['stop' => true]));
    }

    /**
     * Get and remove a received payload of the given class, if any.
     *
     * @param  class-string<\LaraGram\Surge\RoadRunner\Worker\Payload>|null  $class
     */
    public function getPayload(?string $class = null): ?Payload
    {
        if (($position = $this->findPayload($class)) === null) {
            return null;
        }

        $payload = $this->payloads[$position];

        unset($this->payloads[$position]);

        return $payload;
    }

    /**
     * Find the position of a received payload of the given class.
     *
     * @param  class-string<\LaraGram\Surge\RoadRunner\Worker\Payload>|null  $class
     */
    protected function findPayload(?string $class = null): ?int
    {
        foreach ($this->payloads as $position => $payload) {
            if ($class === null || $payload::class === $class) {
                return $position;
            }
        }

        while (($payload = $this->pullPayload()) !== null && ! $payload instanceof Pong) {
            $this->payloads[] = $payload;

            if ($class === null || $payload::class === $class) {
                return array_key_last($this->payloads);
            }
        }

        return null;
    }

    /**
     * Pull a payload when one is available without blocking.
     */
    protected function pullPayload(): ?Payload
    {
        if (! $this->waitingPong && $this->relay instanceof BlockingRelayInterface) {
            if ($this->streamMode) {
                $this->haveToPing();
            }

            return null;
        }

        if (! $this->relay->hasFrame()) {
            return null;
        }

        $payload = PayloadFactory::fromFrame($this->relay->waitFrame());

        if ($payload instanceof Pong) {
            $this->waitingPong = false;

            return null;
        }

        return $payload;
    }

    /**
     * Send the given body and header to the server.
     */
    protected function send(string $body = '', string $header = '', bool $eos = true, ?int $codec = null): void
    {
        $frame = new Frame($header.$body, [strlen($header)]);

        if (! $eos) {
            $frame->byte10 |= Frame::BYTE10_STREAM;
        }

        if ($this->shouldPing) {
            $frame->byte10 |= Frame::BYTE10_PING;
        }

        if ($codec !== null) {
            $frame->setFlag($codec);
        }

        $this->sendFrame($frame);
    }

    /**
     * Send the given frame to the server.
     */
    protected function sendFrame(Frame $frame): void
    {
        try {
            if ($this->streamMode && ($frame->byte10 & Frame::BYTE10_STREAM) && $this->shouldPing) {
                $frame->byte10 |= Frame::BYTE10_PING;
                $this->shouldPing = false;
                $this->waitingPong = true;
            }

            $this->relay->send($frame);
        } catch (GoridgeException $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        } catch (Throwable $e) {
            throw new RoadRunnerException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Encode the given control payload.
     */
    protected function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Ping the server every fifth stream frame, to detect a stopped stream.
     */
    protected function haveToPing(): void
    {
        if (! $this->waitingPong && $this->framesSent > 0 && $this->framesSent % 5 === 0) {
            $this->shouldPing = true;
        }
    }
}
