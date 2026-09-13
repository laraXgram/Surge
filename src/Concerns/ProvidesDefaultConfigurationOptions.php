<?php

namespace LaraGram\Surge\Concerns;

trait ProvidesDefaultConfigurationOptions
{
    /**
     * Get the listeners that will prepare the LaraGram application for a new request.
     */
    public static function prepareApplicationForNextRequest(): array
    {
        return [
            \LaraGram\Surge\Listeners\FlushLocaleState::class,
            \LaraGram\Surge\Listeners\FlushQueuedCookies::class,
            \LaraGram\Surge\Listeners\FlushSessionState::class,
            \LaraGram\Surge\Listeners\FlushAuthenticationState::class,
            \LaraGram\Surge\Listeners\EnforceRequestScheme::class,
            \LaraGram\Surge\Listeners\EnsureRequestServerPortMatchesScheme::class,
            \LaraGram\Surge\Listeners\GiveNewRequestInstanceToApplication::class,
            \LaraGram\Surge\Listeners\GiveNewRequestInstanceToPaginator::class,
        ];
    }

    /**
     * Get the listeners that will prepare the LaraGram application for a new operation.
     */
    public static function prepareApplicationForNextOperation(): array
    {
        return [
            \LaraGram\Surge\Listeners\CreateConfigurationSandbox::class,
            \LaraGram\Surge\Listeners\CreateUrlGeneratorSandbox::class,
            \LaraGram\Surge\Listeners\CreatePathGeneratorSandbox::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToAuthorizationGate::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToDatabaseManager::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToDatabaseSessionHandler::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToFilesystemManager::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToHttpKernel::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToBotKernel::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToLogManager::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToPipelineHub::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToCacheManager::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToSessionManager::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToQueueManager::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToListener::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToRouter::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToValidationFactory::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToTemplateFactory::class,
            \LaraGram\Surge\Listeners\GiveNewApplicationInstanceToViewFactory::class,
            \LaraGram\Surge\Listeners\FlushDatabaseRecordModificationState::class,
            \LaraGram\Surge\Listeners\FlushDatabaseQueryLog::class,
            \LaraGram\Surge\Listeners\RefreshQueryDurationHandling::class,
            \LaraGram\Surge\Listeners\FlushArrayCache::class,
            \LaraGram\Surge\Listeners\FlushLogContext::class,
            \LaraGram\Surge\Listeners\FlushLogState::class,
            \LaraGram\Surge\Listeners\FlushStrCache::class,
            \LaraGram\Surge\Listeners\FlushTranslatorCache::class,
            \LaraGram\Surge\Listeners\FlushVite::class,

            // First-Party Packages...
            \LaraGram\Surge\Listeners\PrepareLunaForNextOperation::class,
        ];
    }

    /**
     * Get the container bindings / services that should be pre-resolved by default.
     */
    public static function defaultServicesToWarm(): array
    {
        return [
            'auth',
            'cache',
            'cache.store',
            'config',
            'cookie',
            'db',
            'db.factory',
            'db.transactions',
            'encrypter',
            'files',
            'hash',
            'log',
            'listener',
            'listener.path',
            'listens',
            'router',
            'routes',
            'session',
            'session.store',
            'template',
            'translator',
            'url',
            'view',
        ];
    }
}
