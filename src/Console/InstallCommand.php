<?php

namespace Shah\Guardian\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'guardian:install
                            {--force : Overwrite any existing files}
                            {--skip-npm : Skip the npm build process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Guardian package resources';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing Guardian...');

        // Publish configuration
        $this->publishConfiguration();

        // Publish migrations
        $this->publishMigrations();

        // Build and publish assets
        if (!$this->option('skip-npm')) {
            $this->buildAssets();
        }

        // Publish JavaScript assets
        $this->publishJavaScriptAssets();

        // Create necessary directories
        $this->createDirectories();

        $this->info('Guardian installation complete!');
        $this->newLine();
        $this->info('Please run the following command to run migrations:');
        $this->comment('    php artisan migrate');
        $this->newLine();
        $this->info('For documentation, please visit:');
        $this->comment('    https://github.com/codemystify/guardian');

        return self::SUCCESS;
    }

    /**
     * Publish the configuration file.
     */
    protected function publishConfiguration(): void
    {
        $this->comment('Publishing configuration...');

        if ($this->configExists('guardian.php') && ! $this->option('force')) {
            if (! $this->confirm('The guardian.php configuration file already exists. Do you want to replace it?')) {
                $this->info('Existing configuration was not replaced.');

                return;
            }
        }

        $this->callSilently('vendor:publish', [
            '--tag' => 'guardian-config',
            '--force' => $this->option('force'),
        ]);

        $this->info('Guardian configuration published.');
    }

    /**
     * Publish the migration files.
     */
    protected function publishMigrations(): void
    {
        $this->comment('Publishing migrations...');

        $this->callSilently('vendor:publish', [
            '--tag' => 'guardian-migrations',
            '--force' => $this->option('force'),
        ]);

        $this->info('Guardian migrations published.');
    }

    /**
     * Build the JavaScript assets.
     */
    protected function buildAssets(): void
    {
        $this->comment('Building JavaScript assets...');

        $packagePath = dirname(__DIR__, 2); // Go up two directories to the package root

        // Check if package.json exists
        if (!file_exists($packagePath . '/package.json')) {
            $this->warn('No package.json found. Skipping the npm build process.');
            return;
        }

        // Check if npm is installed
        $npmInstalled = Process::fromShellCommandline('which npm')
                               ->run() === 0;

        if (!$npmInstalled) {
            $this->warn('npm is not installed. Skipping the npm build process.');
            return;
        }

        // Install npm dependencies
        $this->info('Installing npm dependencies...');

        $process = Process::fromShellCommandline('npm install', $packagePath);
        $process->setTimeout(null); // No timeout

        if ($this->output->isVerbose()) {
            $process->setTty(true);
            $process->run();
        } else {
            $this->output->write('Installing npm dependencies... ');
            $process->run();
            $this->output->writeln('Done!');
        }

        if (!$process->isSuccessful()) {
            $this->error('Failed to install npm dependencies.');
            $this->error($process->getErrorOutput());
            return;
        }

        // Build the assets
        $this->info('Building assets...');

        $process = Process::fromShellCommandline('npm run build', $packagePath);
        $process->setTimeout(null); // No timeout

        if ($this->output->isVerbose()) {
            $process->setTty(true);
            $process->run();
        } else {
            $this->output->write('Building assets... ');
            $process->run();
            $this->output->writeln('Done!');
        }

        if (!$process->isSuccessful()) {
            $this->error('Failed to build assets.');
            $this->error($process->getErrorOutput());
            return;
        }

        $this->info('JavaScript assets built successfully.');
    }

    /**
     * Publish the JavaScript assets.
     */
    protected function publishJavaScriptAssets(): void
    {
        $this->comment('Publishing JavaScript assets...');

        // Ensure all asset directories are published
        $this->callSilently('vendor:publish', [
            '--tag' => 'guardian-assets',
            '--force' => $this->option('force'),
        ]);

        // Copy the main guardian.js to the vendor directory (backwards compatibility)
        $sourceFile = dirname(__DIR__, 2) . '/dist/js/guardian.js';
        $destinationFile = public_path('vendor/guardian.js');

        if (file_exists($destinationFile) && !$this->option('force')) {
            if (!$this->confirm('The guardian.js file already exists. Do you want to replace it?')) {
                $this->info('Existing file was not replaced.');
                return;
            }
        }

        // Ensure the vendor directory exists
        if (!is_dir(dirname($destinationFile))) {
            (new Filesystem)->makeDirectory(dirname($destinationFile), 0755, true);
        }

        // Copy the file
        if (file_exists($sourceFile)) {
            copy($sourceFile, $destinationFile);
            $this->info('Guardian JavaScript assets published.');
        } else {
            $this->warn('Source JavaScript file not found: ' . $sourceFile);
            $this->warn('This is normal if you skipped the npm build process.');

            // Check if we have the pre-built file in the resources directory
            $fallbackFile = dirname(__DIR__, 2) . '/resources/js/guardian.js';
            if (file_exists($fallbackFile)) {
                copy($fallbackFile, $destinationFile);
                $this->info('Used pre-built JavaScript asset instead.');
            } else {
                $this->error('No JavaScript assets available. Please run with --skip-npm=false to build assets.');
            }
        }
    }

    /**
     * Create necessary directories for the package.
     */
    protected function createDirectories(): void
    {
        $this->comment('Creating necessary directories...');

        // Create the logs directory
        $this->ensureDirectoryExists(storage_path('logs/guardian'));

        // Create the cache directory
        $this->ensureDirectoryExists(storage_path('framework/cache/guardian'));

        $this->info('Guardian directories created.');
    }

    /**
     * Check if a configuration file already exists.
     */
    protected function configExists(string $fileName): bool
    {
        return file_exists(config_path($fileName));
    }

    /**
     * Ensure the given directory exists.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            (new Filesystem)->makeDirectory($path, 0755, true);
        }
    }
}
