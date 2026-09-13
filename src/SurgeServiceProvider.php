<?php

namespace LaraGram\Surge;

use LaraGram\Contracts\Encryption\DecryptException;
use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Http\Request;
use LaraGram\Http\Response;
use LaraGram\Support\Facades\Cache;
use LaraGram\Support\Facades\Crypt;
use LaraGram\Support\Facades\Event;
use LaraGram\Support\ServiceProvider;
use LaraGram\Surge\Cache\SurgeArrayStore;
use LaraGram\Surge\Cache\SurgeStore;
use LaraGram\Surge\Contracts\DispatchesCoroutines;
use LaraGram\Surge\Events\TickReceived;
use LaraGram\Surge\Exceptions\DdException;
use LaraGram\Surge\Exceptions\TaskException;
use LaraGram\Surge\Exceptions\TaskTimeoutException;
use LaraGram\Surge\Facades\Surge as SurgeFacade;
use LaraGram\Surge\FrankenPhp\ServerProcessInspector as FrankenPhpServerProcessInspector;
use LaraGram\Surge\FrankenPhp\ServerStateFile as FrankenPhpServerStateFile;
use LaraGram\Surge\RoadRunner\ServerProcessInspector as RoadRunnerServerProcessInspector;
use LaraGram\Surge\RoadRunner\ServerStateFile as RoadRunnerServerStateFile;
use LaraGram\Surge\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use LaraGram\Surge\Swoole\ServerStateFile as SwooleServerStateFile;
use LaraGram\Surge\Swoole\SignalDispatcher;
use LaraGram\Surge\Swoole\SwooleCoroutineDispatcher;
use LaraGram\Surge\Swoole\SwooleTaskDispatcher;

class SurgeServiceProvider extends ServiceProvider
{
    /**
     * Register Surge's services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/surge.php', 'surge');

        $this->bindListeners();

        $this->app->singleton('surge', Surge::class);

        $this->app->bind(RoadRunnerServerProcessInspector::class, function ($app) {
            return new RoadRunnerServerProcessInspector(
                $app->make(RoadRunnerServerStateFile::class),
                new ProcessFactory,
                new PosixExtension,
            );
        });

        $this->app->bind(RoadRunnerServerStateFile::class, function ($app) {
            return new RoadRunnerServerStateFile($app['config']->get(
                'surge.state_file',
                storage_path('logs/surge-server-state.json')
            ));
        });

        $this->app->bind(SwooleServerProcessInspector::class, function ($app) {
            return new SwooleServerProcessInspector(
                $app->make(SignalDispatcher::class),
                $app->make(SwooleServerStateFile::class),
                $app->make(Exec::class),
            );
        });

        $this->app->bind(SwooleServerStateFile::class, function ($app) {
            return new SwooleServerStateFile($app['config']->get(
                'surge.state_file',
                storage_path('logs/surge-server-state.json')
            ));
        });

        $this->app->bind(FrankenPhpServerProcessInspector::class, function ($app) {
            return new FrankenPhpServerProcessInspector(
                $app->make(FrankenPhpServerStateFile::class)
            );
        });

        $this->app->bind(FrankenPhpServerStateFile::class, function ($app) {
            return new FrankenPhpServerStateFile($app['config']->get(
                'surge.state_file',
                storage_path('logs/surge-server-state.json')
            ));
        });

        $this->app->bind(DispatchesCoroutines::class, function ($app) {
            return class_exists('Swoole\Http\Server')
                        ? new SwooleCoroutineDispatcher($app->bound('Swoole\Http\Server'))
                        : $app->make(SequentialCoroutineDispatcher::class);
        });

        Surge::registerDevCommands();
    }

    /**
     * Bootstrap Surge's services.
     *
     * @return void
     */
    public function boot()
    {
        $dispatcher = $this->app[Dispatcher::class];

        foreach ($this->app['config']->get('surge.listeners', []) as $event => $listeners) {
            foreach (array_filter(array_unique($listeners)) as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }

        $this->registerCacheDriver();
        $this->registerCommands();
        $this->registerHttpTaskHandlingRoutes();
        $this->registerPublishing();
    }

    /**
     * Bind the Surge event listeners in the container.
     *
     * @return void
     */
    protected function bindListeners()
    {
        $this->app->singleton(Listeners\CollectGarbage::class);
        $this->app->singleton(Listeners\CreateConfigurationSandbox::class);
        $this->app->singleton(Listeners\CreatePathGeneratorSandbox::class);
        $this->app->singleton(Listeners\CreateUrlGeneratorSandbox::class);
        $this->app->singleton(Listeners\DisconnectFromDatabases::class);
        $this->app->singleton(Listeners\EnforceRequestScheme::class);
        $this->app->singleton(Listeners\EnsureRequestServerPortMatchesScheme::class);
        $this->app->singleton(Listeners\EnsureUploadedFilesAreValid::class);
        $this->app->singleton(Listeners\EnsureUploadedFilesCanBeMoved::class);
        $this->app->singleton(Listeners\FlushAuthenticationState::class);
        $this->app->singleton(Listeners\FlushQueuedCookies::class);
        $this->app->singleton(Listeners\FlushSessionState::class);
        $this->app->singleton(Listeners\FlushTemporaryContainerInstances::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToAuthorizationGate::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToBotKernel::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToHttpKernel::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToListener::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToLogManager::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToPipelineHub::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToQueueManager::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToRouter::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToTemplateFactory::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToValidationFactory::class);
        $this->app->singleton(Listeners\GiveNewApplicationInstanceToViewFactory::class);
        $this->app->singleton(Listeners\GiveNewRequestInstanceToApplication::class);
        $this->app->singleton(Listeners\GiveNewRequestInstanceToPaginator::class);
        $this->app->singleton(Listeners\PrepareLunaForNextOperation::class);
        $this->app->singleton(Listeners\ReportException::class);
        $this->app->singleton(Listeners\StopWorkerIfNecessary::class);
    }

    /**
     * Register the Surge cache driver.
     *
     * @return void
     */
    protected function registerCacheDriver()
    {
        if (empty($this->app['config']['surge.cache'])) {
            return;
        }

        $store = $this->app->bound('surge.cacheTable')
                        ? new SurgeStore($this->app['surge.cacheTable'])
                        : new SurgeArrayStore;

        Event::listen(TickReceived::class, fn () => $store->refreshIntervalCaches());

        Cache::extend('surge', fn () => Cache::repository($store));
    }

    /**
     * Register the commands offered by Surge.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
                Commands\StartCommand::class,
                Commands\StartRoadRunnerCommand::class,
                Commands\StartSwooleCommand::class,
                Commands\StartFrankenPhpCommand::class,
                Commands\ReloadCommand::class,
                Commands\StatusCommand::class,
                Commands\StopCommand::class,
            ]);

            if (method_exists($this, 'reloads')) {
                $this->reloads('surge:reload', 'surge');
            }
        }
    }

    /**
     * Register the Surge routes that handle tasks from invokers not in a Server context.
     *
     * @return void
     */
    protected function registerHttpTaskHandlingRoutes()
    {
        SurgeFacade::route('POST', '/surge/resolve-tasks', function (Request $request) {
            try {
                return new Response(serialize((new SwooleTaskDispatcher)->resolve(
                    unserialize(Crypt::decryptString($request->input('tasks'))),
                    $request->input('wait')
                )), 200);
            } catch (DecryptException) {
                return new Response('', 403);
            } catch (TaskException|DdException) {
                return new Response('', 500);
            } catch (TaskTimeoutException) {
                return new Response('', 504);
            }
        });

        SurgeFacade::route('POST', '/surge/dispatch-tasks', function (Request $request) {
            try {
                (new SwooleTaskDispatcher)->dispatch(
                    unserialize(Crypt::decryptString($request->input('tasks'))),
                );
            } catch (DecryptException) {
                return new Response('', 403);
            }

            return new Response('', 200);
        });
    }

    /**
     * Register Surge's publishing.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/surge.php' => config_path('surge.php'),
            ], 'surge-config');
        }
    }
}
