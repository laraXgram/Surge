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
            options: ['swoole'],
            default: 'swoole'
        );

        return (int) ! tap(match ($server) {
            'swoole' => $this->installSwooleServer(),
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
                $this->newLine();
                $this->components->warn('Please adjust the `SURGE_SERVER` environment variable.');
            }
        }
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
