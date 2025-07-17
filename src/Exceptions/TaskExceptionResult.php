<?php

namespace LaraGram\Surge\Exceptions;

use LaraGram\Support\SerializableClosure\Support\ClosureStream;

class TaskExceptionResult
{
    public function __construct(
        protected string $class,
        protected string $message,
        protected int $code,
        protected string $file,
        protected int $line,
    ) {
    }

    /**
     * Creates a new task exception result from the given throwable.
     *
     * @param  \Throwable  $throwable
     * @return \LaraGram\Surge\Exceptions\TaskExceptionResult
     */
    public static function from($throwable)
    {
        $fallbackTrace = str_starts_with($throwable->getFile(), ClosureStream::STREAM_PROTO.'://')
            ? collect($throwable->getTrace())->whereNotNull('file')->first()
            : null;

        return new static(
            $throwable::class,
            $throwable->getMessage(),
            (int) $throwable->getCode(),
            $fallbackTrace['file'] ?? $throwable->getFile(),
            $fallbackTrace['line'] ?? (int) $throwable->getLine(),
        );
    }

    /**
     * Gets the original throwable.
     *
     * @return \LaraGram\Surge\Exceptions\TaskException
     */
    public function getOriginal()
    {
        return new TaskException(
            $this->class,
            $this->message,
            (int) $this->code,
            $this->file,
            (int) $this->line,
        );
    }
}
