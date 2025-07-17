<?php

use LaraGram\Surge\Contracts\OperationTerminated;
use LaraGram\Surge\Events\RequestHandled;
use LaraGram\Surge\Events\RequestReceived;
use LaraGram\Surge\Events\RequestTerminated;
use LaraGram\Surge\Events\TaskReceived;
use LaraGram\Surge\Events\TaskTerminated;
use LaraGram\Surge\Events\TickReceived;
use LaraGram\Surge\Events\TickTerminated;
use LaraGram\Surge\Events\WorkerErrorOccurred;
use LaraGram\Surge\Events\WorkerStarting;
use LaraGram\Surge\Events\WorkerStopping;
use LaraGram\Surge\Listeners\FlushOnce;
use LaraGram\Surge\Listeners\FlushTemporaryContainerInstances;
use LaraGram\Surge\Listeners\ReportException;
use LaraGram\Surge\Listeners\StopWorkerIfNecessary;
use LaraGram\Surge\Surge;

return [

    /*
    |--------------------------------------------------------------------------
    | Surge Server
    |--------------------------------------------------------------------------
    |
    | This value determines the default "server" that will be used by Surge
    | when starting, restarting, or stopping your server via the CLI. You
    | are free to change this to the supported server of your choosing.
    |
    | Supported: "swoole"
    |
    */

    'server' => env('SURGE_SERVER', 'swoole'),

    /*
    |--------------------------------------------------------------------------
    | Force HTTPS
    |--------------------------------------------------------------------------
    |
    | When this configuration value is set to "true", Surge will inform the
    | framework that all absolute links must be generated using the HTTPS
    | protocol. Otherwise your links may be generated using plain HTTP.
    |
    */

    'https' => env('SURGE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Surge Listeners
    |--------------------------------------------------------------------------
    |
    | All of the event listeners for Surge's events are defined below. These
    | listeners are responsible for resetting your application's state for
    | the next request. You may even add your own listeners to the list.
    |
    */

    'listeners' => [
        WorkerStarting::class => [

        ],

        RequestReceived::class => [
            ...Surge::prepareApplicationForNextOperation(),
            ...Surge::prepareApplicationForNextRequest(),
            //
        ],

        RequestHandled::class => [
            //
        ],

        RequestTerminated::class => [

        ],

        TaskReceived::class => [
            ...Surge::prepareApplicationForNextOperation(),
            //
        ],

        TaskTerminated::class => [
            //
        ],

        TickReceived::class => [
            ...Surge::prepareApplicationForNextOperation(),
            //
        ],

        TickTerminated::class => [
            //
        ],

        OperationTerminated::class => [
            FlushOnce::class,
            FlushTemporaryContainerInstances::class,
            // DisconnectFromDatabases::class,
            // CollectGarbage::class,
        ],

        WorkerErrorOccurred::class => [
            ReportException::class,
            StopWorkerIfNecessary::class,
        ],

        WorkerStopping::class => [

        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm / Flush Bindings
    |--------------------------------------------------------------------------
    |
    | The bindings listed below will either be pre-warmed when a worker boots
    | or they will be flushed before every new request. Flushing a binding
    | will force the container to resolve that binding again when asked.
    |
    */

    'warm' => [
        ...Surge::defaultServicesToWarm(),
    ],

    'flush' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Surge Swoole Tables
    |--------------------------------------------------------------------------
    |
    | While using Swoole, you may define additional tables as required by the
    | application. These tables can be used to store data that needs to be
    | quickly accessed by other workers on the particular Swoole server.
    |
    */

    'tables' => [
        'example:1000' => [
            'name' => 'string:1000',
            'votes' => 'int',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Surge Swoole Cache Table
    |--------------------------------------------------------------------------
    |
    | While using Swoole, you may leverage the Surge cache, which is powered
    | by a Swoole table. You may set the maximum number of rows as well as
    | the number of bytes per row using the configuration options below.
    |
    */

    'cache' => [
        'rows' => 1000,
        'bytes' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watching
    |--------------------------------------------------------------------------
    |
    | The following list of files and directories will be watched when using
    | the --watch option offered by Surge. If any of the directories and
    | files are changed, Surge will automatically reload your workers.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'listener/*.php',
        'composer.lock',
        '.env',
    ],

    /*
    |--------------------------------------------------------------------------
    | Garbage Collection Threshold
    |--------------------------------------------------------------------------
    |
    | When executing long-lived PHP scripts such as Surge, memory can build
    | up before being cleared by PHP. You can force Surge to run garbage
    | collection if your application consumes this amount of megabytes.
    |
    */

    'garbage' => 50,

    /*
    |--------------------------------------------------------------------------
    | Maximum Execution Time
    |--------------------------------------------------------------------------
    |
    | The following setting configures the maximum execution time for requests
    | being handled by Surge. You may set this value to 0 to indicate that
    | there isn't a specific time limit on Surge request execution time.
    |
    */

    'max_execution_time' => 30,

];
