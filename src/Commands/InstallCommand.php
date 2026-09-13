<?php

namespace LaraGram\Surge\Commands;

use LaraGram\Support\Facades\File;
use LaraGram\Support\Str;
use LaraGram\Surge\Swoole\SwooleExtension;
use LaraGram\Console\Attribute\AsCommand;
use Throwable;

use function LaraGram\Console\Prompts\select;

#[AsCommand(name: 'surge:install')]
class InstallCommand extends Command
{
    use Concerns\InstallsFrankenPhpDependencies,
        Concerns\InstallsRoadRunnerDependencies;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'surge:install
                    {--server= : The server that should be used to serve the application}
                    {--force : Overwrite any existing configuration files}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Install the Surge components and resources';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle()
    {
        $server = $this->option('server') ?: select(
            label: 'Which application server you would like to use?',
            options: ['frankenphp', 'roadrunner', 'swoole'],
            default: 'swoole'
        );

        return (int) ! tap(match ($server) {
            'swoole' => $this->installSwooleServer(),
            'roadrunner' => $this->installRoadRunnerServer(),
            'frankenphp' => $this->installFrankenPhpServer(),
            default => $this->invalidServer($server),
        }, function ($installed) use ($server) {
            if ($installed) {
                $this->updateEnvironmentFile($server);

                $this->callSilent('vendor:publish', [
                    '--tag' => 'surge-config',
                    '--force' => $this->option('force'),
                ]);

                $this->components->info('Surge installed successfully.');
                $this->newLine();
            }
        });
    }

    /**
     * Updates the environment file with the given server.
     *
     * @param  string  $server
     * @return void
     */
    public function updateEnvironmentFile($server)
    {
        if (File::exists($env = app()->environmentFile())) {
            $contents = File::get($env);

            if (! Str::contains($contents, 'SURGE_SERVER=')) {
                File::append(
                    $env,
                    PHP_EOL.'SURGE_SERVER='.$server.PHP_EOL,
                );
            } else {
                File::put($env, preg_replace(
                    '/^SURGE_SERVER=.*/m',
                    'SURGE_SERVER='.$server,
                    $contents,
                ));
            }
        }
    }

    /**
     * Install the RoadRunner dependencies.
     *
     * @return bool
     */
    public function installRoadRunnerServer()
    {
        if (! $this->ensureRoadRunnerPackageIsInstalled()) {
            return false;
        }

        if (File::exists(base_path('.gitignore'))) {
            collect(['rr', '.rr.yaml'])
                ->each(function ($file) {
                    $contents = File::get(base_path('.gitignore'));
                    if (! Str::contains($contents, $file.PHP_EOL)) {
                        File::append(
                            base_path('.gitignore'),
                            $file.PHP_EOL
                        );
                    }
                });
        }

        return $this->ensureRoadRunnerBinaryIsInstalled();
    }

    /**
     * Install the Swoole dependencies.
     *
     * @return bool
     */
    public function installSwooleServer()
    {
        if (! ($extension = resolve(SwooleExtension::class))->isInstalled()) {
            $this->components->warn('The Swoole/OpenSwoole extension is missing.');
        }

        if (! $extension->installIdeHelper()) {
            $this->components->warn('Failed to install the Swoole/OpenSwoole IDE helper.');
        }

        return true;
    }

    /**
     * Install the FrankenPHP server.
     *
     * @return bool
     */
    public function installFrankenPhpServer()
    {
        $gitIgnorePath = base_path('.gitignore');

        if (File::exists($gitIgnorePath)) {
            $contents = File::get($gitIgnorePath);

            $filesToAppend = collect(['**/caddy', 'frankenphp', 'frankenphp-worker.php'])
                ->filter(fn ($file) => ! str_contains($contents, $file.PHP_EOL))
                ->implode(PHP_EOL);

            if ($filesToAppend !== '') {
                File::append($gitIgnorePath, PHP_EOL.$filesToAppend.PHP_EOL);
            }
        }

        $this->ensureFrankenPhpWorkerIsInstalled();

        try {
            $this->ensureFrankenPhpBinaryIsInstalled();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Inform the user that the server type is invalid.
     *
     * @return bool
     */
    protected function invalidServer(string $server)
    {
        $this->components->error("Invalid server: {$server}.");

        return false;
    }
}
