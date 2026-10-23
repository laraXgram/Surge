<?php

namespace LaraGram\Surge\RoadRunner\Worker;

use LaraGram\Http\VarDumper\Dumper\AbstractDumper;
use LaraGram\Http\VarDumper\Dumper\CliDumper;
use LaraGram\Http\VarDumper\Dumper\HtmlDumper;

/**
 * Redirects output written outside of responses to STDERR, so it can't corrupt the pipes relay.
 *
 * @internal
 */
final class StdoutHandler
{
    private const PIPE_OUT = 'php://stderr';

    private const ERROR_WRITING_HEADER = 'Could not explicitly send a headers "%s" using PHP header() function. '.
        'Please use RoadRunner response object instead';

    /**
     * Start redirecting the output.
     */
    public static function register(int $chunkSize = 1): void
    {
        ob_start(static function (string $chunk, int $phase): void {
            if (($phase & PHP_OUTPUT_HANDLER_WRITE) === PHP_OUTPUT_HANDLER_WRITE && $chunk !== '') {
                file_put_contents(self::PIPE_OUT, $chunk);
            }
        }, $chunkSize);

        header_register_callback(static function (): void {
            if (headers_list() !== []) {
                file_put_contents(self::PIPE_OUT, self::ERROR_WRITING_HEADER);
            }
        });

        if (class_exists(AbstractDumper::class)) {
            AbstractDumper::$defaultOutput = self::PIPE_OUT;
            CliDumper::$defaultOutput = self::PIPE_OUT;
            HtmlDumper::$defaultOutput = self::PIPE_OUT;
        }
    }
}
