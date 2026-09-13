<?php

namespace LaraGram\Surge\Commands\Concerns;

use LaraGram\Support\Str;
use LaraGram\Surge\RoadRunner\Concerns\FindsRoadRunnerBinary;
use RuntimeException;
use Spiral\RoadRunner\Http\PSR7Worker;
use LaraGram\Console\Process\Exception\ProcessSignaledException;
use LaraGram\Console\Process\ExecutableFinder;
use LaraGram\Console\Process\PhpExecutableFinder;
use LaraGram\Console\Process\Process;
use Throwable;

use function LaraGram\Console\Prompts\confirm;

trait InstallsRoadRunnerDependencies
{
    use FindsRoadRunnerBinary;

    /**
     * The minimum required version of the RoadRunner binary.
     *
     * @var string
     */
    protected $requiredRoadRunnerVersion = '2023.3.0';

    /**
     * Determine if RoadRunner is installed.
     *
     * @return bool
     */
    protected function isRoadRunnerInstalled()
    {
        return class_exists(PSR7Worker::class);
    }

    /**
     * Ensure the RoadRunner package is installed into the project.
     *
     * @return bool
     */
    protected function ensureRoadRunnerPackageIsInstalled()
    {
        if ($this->isRoadRunnerInstalled()) {
            return true;
        }

        if (! confirm('Surge requires "spiral/roadrunner-http:^3.3.0" and "spiral/roadrunner-cli:^2.6.0". Do you wish to install them as a dependencies?')) {
            $this->components->error('Surge requires "spiral/roadrunner-http" and "spiral/roadrunner-cli".');

            return false;
        }

        $command = $this->findComposer().' require spiral/roadrunner-http:^3.3.0 spiral/roadrunner-cli:^2.6.0 --with-all-dependencies';

        $process = Process::fromShellCommandline($command, null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('Warning: '.$e->getMessage());
            }
        }

        try {
            $process->run(function ($type, $line) {
                $this->output->write($line);
            });
        } catch (ProcessSignaledException $e) {
            if (extension_loaded('pcntl') && $e->getSignal() !== SIGINT) {
                throw $e;
            }
        }

        return true;
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd().'/composer.phar';

        $phpPath = (new PhpExecutableFinder)->find();

        if (! file_exists($composerPath)) {
            $composerPath = (new ExecutableFinder())->find('composer');
        }

        return '"'.$phpPath.'" "'.$composerPath.'"';
    }

    /**
     * Ensure the RoadRunner binary is installed into the project.
     */
    protected function ensureRoadRunnerBinaryIsInstalled(): string
    {
        if (! is_null($roadRunnerBinary = $this->findRoadRunnerBinary())) {
            return $roadRunnerBinary;
        }

        if (confirm('Unable to locate RoadRunner binary. Should Surge download the binary for your operating system?', true)) {
            $this->downloadRoadRunnerBinary();

            copy(__DIR__.'/../stubs/rr.yaml', base_path('.rr.yaml'));
        }

        return base_path('rr');
    }

    /**
     * Ensure the RoadRunner binary installed in your project meets Surge requirements.
     *
     * @param  string  $roadRunnerBinary
     * @return void
     */
    protected function ensureRoadRunnerBinaryMeetsRequirements($roadRunnerBinary)
    {
        $version = tap(new Process([$roadRunnerBinary, '--version'], base_path()))
            ->run()
            ->getOutput();

        if (! Str::startsWith($version, 'rr version')) {
            return $this->components->warn(
                'Unable to determine the current RoadRunner binary version. Please report this issue: https://github.com/laraxgram/surge/issues/new.'
            );
        }

        $version = explode(' ', $version)[2];

        if (version_compare($version, $this->requiredRoadRunnerVersion, '>=')) {
            return;
        }

        $this->components->warn("Your RoadRunner binary version (<fg=red>$version</>) may be incompatible with Surge.");

        if (confirm('Should Surge download the latest RoadRunner binary version for your operating system?', true)) {
            rename($roadRunnerBinary, "$roadRunnerBinary.backup");

            try {
                $this->downloadRoadRunnerBinary();
            } catch (Throwable $e) {
                report($e);

                rename("$roadRunnerBinary.backup", $roadRunnerBinary);

                return $this->components->warn('Unable to download RoadRunner binary. The HTTP request exception has been logged.');
            }

            unlink("$roadRunnerBinary.backup");
        }
    }

    /**
     * Download the latest version of the RoadRunner binary.
     *
     * @return void
     */
    protected function downloadRoadRunnerBinary()
    {
        $installed = false;

        tap(new Process(array_filter([
            (new PhpExecutableFinder)->find(),
            './vendor/bin/rr',
            'get-binary',
            '-n',
            '--ansi',
        ]), base_path(), null, null, null))->mustRun(function (string $type, string $buffer) use (&$installed) {
            if (! $installed) {
                $this->output->write($buffer);

                $installed = str_contains($buffer, 'has been installed into');
            }
        });

        chmod(base_path('rr'), 0755);

        $this->line('');
    }
}
