<?php

namespace Shah\Guardian\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class DashboardCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'guardian:dashboard
                            {--force : Overwrite any existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Guardian dashboard assets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Publishing Guardian Dashboard assets...');

        // Publish dashboard views
        $this->publishViews();

        // Publish dashboard assets (CSS, JS)
        $this->publishAssets();

        // Add dashboard route if not exists
        $this->addDashboardRoute();

        $this->info('Guardian Dashboard installed successfully!');
        $this->newLine();
        $this->info('You can access the dashboard at:');
        $this->comment('    /__guardian/dashboard');
        $this->newLine();
        $this->info('You can customize the dashboard route in config/guardian.php');

        return self::SUCCESS;
    }

    /**
     * Publish the dashboard views.
     */
    protected function publishViews(): void
    {
        $this->comment('Publishing dashboard views...');

        $this->callSilently('vendor:publish', [
            '--tag' => 'guardian-views',
            '--force' => $this->option('force'),
        ]);

        $this->info('Dashboard views published.');
    }

    /**
     * Publish dashboard assets (CSS, JS).
     */
    protected function publishAssets(): void
    {
        $this->comment('Publishing dashboard assets...');

        // Ensure directory exists
        $this->ensureDirectoryExists(public_path('vendor/guardian/dashboard'));

        // Copy dashboard assets
        $this->copyDirectory(
            __DIR__.'/../../resources/dist/dashboard',
            public_path('vendor/guardian/dashboard')
        );

        $this->info('Dashboard assets published.');
    }

    /**
     * Add dashboard route if not exists.
     */
    protected function addDashboardRoute(): void
    {
        $routesPath = base_path('routes/web.php');

        if (! file_exists($routesPath)) {
            $this->warn('Routes file not found. Skipping route addition.');

            return;
        }

        $routeContent = file_get_contents($routesPath);
        $dashboardRoute = "Route::get('__guardian/dashboard', '\Shah\Guardian\Http\Controllers\DashboardController@index')->middleware(['web', 'auth'])->name('guardian.dashboard');";

        if (strpos($routeContent, '__guardian/dashboard') !== false) {
            $this->comment('Dashboard route already exists. Skipping...');

            return;
        }

        // Get the route file content
        $routeContent = file_get_contents($routesPath);

        // Add the new route at the end
        $updatedContent = $routeContent."\n\n// Guardian Dashboard Route\n".$dashboardRoute."\n";

        // Write the updated content back to the file
        file_put_contents($routesPath, $updatedContent);

        $this->info('Dashboard route added to routes/web.php');
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

    /**
     * Copy a directory recursively.
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($source)) {
            $this->warn("Source directory not found: {$source}");

            return;
        }

        (new Filesystem)->copyDirectory($source, $destination);
    }
}
