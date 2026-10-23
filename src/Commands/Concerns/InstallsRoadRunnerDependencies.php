<?php

namespace LaraGram\Surge\Commands\Concerns;

use LaraGram\Console\Process\Process;
use LaraGram\Http\Client\Core\Client;
use LaraGram\Support\Facades\Http;
use LaraGram\Support\Str;
use LaraGram\Surge\RoadRunner\Concerns\FindsRoadRunnerBinary;
use PharData;
use RuntimeException;
use Throwable;

use function LaraGram\Console\Prompts\confirm;

trait InstallsRoadRunnerDependencies
{
    use FindsRoadRunnerBinary;

    /**
     * The minimum required version of the RoadRunner binary.
     *
     * RoadRunner moved from calendar versions (2023.3.0) to semantic versions (3.0.0) after 2025.1.
     *
     * @var string
     */
    protected $requiredRoadRunnerVersion = '2023.3.0';

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

        return base_path($this->roadRunnerBinaryName());
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

        if ($this->roadRunnerVersionIsSupported($version)) {
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
     * Determine if the given RoadRunner version is supported.
     */
    protected function roadRunnerVersionIsSupported(string $version): bool
    {
        // Semantic versions (3.x and later) were released after the last calendar version...
        if ((int) $version < 2000) {
            return true;
        }

        return version_compare($version, $this->requiredRoadRunnerVersion, '>=');
    }

    /**
     * Download the latest version of the RoadRunner binary.
     *
     * @return string
     */
    protected function downloadRoadRunnerBinary()
    {
        $githubProxyPrefix = env('SURGE_GITHUB_PROXY_PREFIX', '');

        $release = Http::accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->get($githubProxyPrefix.'https://api.github.com/repos/roadrunner-server/roadrunner/releases/latest')
            ->throw(fn () => $this->components->error('Failed to download RoadRunner.'));

        $prefix = 'roadrunner-'.ltrim($release['tag_name'], 'v').'-'.$this->roadRunnerPlatform();

        $asset = collect($release['assets'] ?? [])->first(
            fn ($asset) => in_array($asset['name'], ["{$prefix}.tar.gz", "{$prefix}.zip"], true)
        ) ?? throw new RuntimeException("RoadRunner asset [{$prefix}] not found.");

        $archive = storage_path('framework/'.$asset['name']);

        $progressBar = null;

        (new Client)->get($githubProxyPrefix.$asset['browser_download_url'], [
            'sink' => $archive,
            'progress' => function ($downloadTotal, $downloadedBytes) use (&$progressBar) {
                if ($downloadTotal === 0) {
                    return;
                }

                if ($progressBar === null) {
                    $progressBar = $this->output->createProgressBar($downloadTotal);
                    $progressBar->setFormat('%percent%% [%bar%] %current%/%max% bytes');
                    $progressBar->start($downloadTotal, $downloadedBytes);

                    return;
                }

                $progressBar->setProgress($downloadedBytes);
            },
        ]);

        $progressBar?->finish();

        $this->newLine();

        try {
            $path = $this->extractRoadRunnerBinary($archive, $prefix);
        } finally {
            @unlink($archive);
        }

        return $path;
    }

    /**
     * Extract the RoadRunner binary from the given release archive into the project.
     */
    protected function extractRoadRunnerBinary(string $archive, string $directory): string
    {
        $binary = $this->roadRunnerBinaryName();
        $extractTo = storage_path('framework/'.$directory);

        try {
            (new PharData($archive))->extractTo($extractTo, "{$directory}/{$binary}", true);

            rename("{$extractTo}/{$directory}/{$binary}", $path = base_path($binary));
        } finally {
            @rmdir("{$extractTo}/{$directory}");
            @rmdir($extractTo);
        }

        chmod($path, 0755);

        return $path;
    }

    /**
     * Get the platform part of the RoadRunner release asset names, e.g. "linux-amd64".
     */
    protected function roadRunnerPlatform(): string
    {
        $os = match (PHP_OS_FAMILY) {
            'Linux' => 'linux',
            'Darwin' => 'darwin',
            'Windows' => 'windows',
            'BSD' => 'freebsd',
            default => throw new RuntimeException('RoadRunner binaries are not available for '.PHP_OS_FAMILY.'.'),
        };

        $arch = match (strtolower(php_uname('m'))) {
            'x86_64', 'amd64' => 'amd64',
            'aarch64', 'arm64' => 'arm64',
            default => throw new RuntimeException('RoadRunner binaries are only available for amd64 and arm64 architectures.'),
        };

        return "{$os}-{$arch}";
    }

    /**
     * Get the file name of the RoadRunner binary.
     */
    protected function roadRunnerBinaryName(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'rr.exe' : 'rr';
    }
}
