<?php

namespace LaraGram\Surge\Commands\Concerns;

use LaraGram\Console\OutputStyle;
use LaraGram\Surge\Commands\Writer\Writer;
use LaraGram\Support\Str;
use LaraGram\Surge\Exceptions\ServerShutdownException;
use LaraGram\Surge\Exceptions\WorkerException;
use LaraGram\Surge\Surge;
use LaraGram\Surge\WorkerExceptionInspector;

trait InteractsWithIO
{
    use InteractsWithTerminal;

    /**
     * A list of error messages that should be ignored.
     *
     * @var array
     */
    protected $ignoreMessages = [
        'destroy signal received',
        'req-resp mode',
        'scan command',
        'sending stop request to the worker',
        'stop signal received, grace timeout is: ',
        'exit forced',
        'worker allocated',
        'worker is allocated',
        'worker constructed',
        'worker destructed',
        'worker destroyed',
        '[INFO] RoadRunner server started; version:',
        '[INFO] sdnotify: not notified',
        'exiting; byeee!!',
        'storage cleaning happened too recently',
        'write error',
        'unable to determine directory for user configuration; falling back to current directory',
        '$HOME environment variable is empty',
        'unable to get instance ID',
    ];

    /**
     * Write a string as raw output.
     *
     * @param  string  $string
     * @return void
     */
    public function raw($string)
    {
        if (! Str::startsWith($string, $this->ignoreMessages)) {
            $this->output instanceof OutputStyle
                ? Surge::writeError($string)
                : $this->output->writeln($string);
        }
    }

    /**
     * Write a string as information output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function info($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'INFO', 'blue', 'white');
    }

    /**
     * Write a string as error output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function error($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'ERROR', 'red', 'white');
    }

    /**
     * Write a string as warning output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function warn($string, $verbosity = null)
    {
        $this->label($string, $verbosity, 'WARN', 'yellow', 'black');
    }

    /**
     * Write a string as label output.
     *
     * @param  string  $string
     * @param  int|string|null  $verbosity
     * @param  string  $level
     * @param  string  $background
     * @param  string  $foreground
     * @return void
     */
    public function label($string, $verbosity, $level, $background, $foreground)
    {
        if (! empty($string) && ! Str::startsWith($string, $this->ignoreMessages)) {
            $this->output->writeln([
                '',
                "  <bg=$background;fg=$foreground;options=bold> $level </> $string",
            ], $this->parseVerbosity($verbosity));
        }
    }

    /**
     * Write information about a request to the console.
     *
     * @param  array  $request
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function requestInfo($request, $verbosity = null)
    {
        $terminalWidth = $this->getTerminalWidth();

        $pattern = $request['pattern'];
        $duration = number_format(round($request['duration'], 2), 2, '.', '');

        $memory = isset($request['memory'])
            ? (number_format($request['memory'] / 1024 / 1024, 2, '.', '').' mb ')
            : '';

        ['method' => $method] = $request;

        $dots = str_repeat('.', max($terminalWidth - strlen($method.$pattern.$duration.$memory) - 16, 0));

        if (empty($dots) && ! $this->output->isVerbose()) {
            $pattern = substr($pattern, 0, $terminalWidth - strlen($method.$duration.$memory) - 15 - 3).'...';
        } else {
            $dots .= ' ';
        }

        $this->output->writeln(sprintf(
            '  <fg=cyan;options=bold>%s</> <options=bold>%s</><fg=#6C7280> %s%s%s ms</>',
            $method,
            $pattern,
            $dots,
            $memory,
            $duration,
        ), $this->parseVerbosity($verbosity));
    }

    /**
     * Write information about a throwable to the console.
     *
     * @param  array  $throwable
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function throwableInfo($throwable, $verbosity = null)
    {
        (new Writer(null, $this->output))->write(
            new WorkerExceptionInspector(
                new WorkerException(
                    $throwable['message'],
                    (int) $throwable['code'],
                    $throwable['file'],
                    (int) $throwable['line'],
                ),
                $throwable['class'],
                $throwable['trace'],
            ),
        );
    }

    /**
     * Write information about a "shutdown" throwable to the console.
     *
     * @param  array  $throwable
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function shutdownInfo($throwable, $verbosity = null)
    {
        $this->throwableInfo($throwable, $verbosity);

        throw new ServerShutdownException;
    }

    /**
     * Handle stream information from the worker.
     *
     * @param  array  $stream
     * @param  int|string|null  $verbosity
     * @return void
     */
    public function handleStream($stream, $verbosity = null)
    {
        match ($stream['type'] ?? null) {
            'request' => $this->requestInfo($stream, $verbosity),
            'throwable' => $this->throwableInfo($stream, $verbosity),
            'shutdown' => $this->shutdownInfo($stream, $verbosity),
            'raw' => $this->raw(json_encode($stream)),
            default => $this->components->info(json_encode($stream), $this->parseVerbosity($verbosity))
        };
    }
}
