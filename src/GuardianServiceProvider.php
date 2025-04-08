<?php

namespace Shah\Guardian;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Shah\Guardian\Console\DashboardCommand;
use Shah\Guardian\Console\InstallCommand;
use Shah\Guardian\Http\Controllers\DashboardController;
use Shah\Guardian\Http\Controllers\ReportController;
use Shah\Guardian\Middleware\GuardianMiddleware;
use Shah\Guardian\Prevention\Protectors\CssLayersProtector;
use Shah\Guardian\Prevention\Protectors\CssObfuscator;
use Shah\Guardian\Prevention\Protectors\HoneytokenInjector;
use Shah\Guardian\Prevention\Protectors\TextFragmenter;
use Shah\Guardian\Prevention\Utilities\DomUtility;
use Shah\Guardian\Prevention\Utilities\ElementPreserver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GuardianServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('guardian')
            ->hasConfigFile()
            ->hasViews()
            ->hasAssets()
            ->hasRoute('web')
            ->hasMigration('create_guardian_logs_table')
            ->hasCommands([
                InstallCommand::class,
                DashboardCommand::class,
            ]);

        // Publish JS assets
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../dist/js' => public_path('vendor/guardian/js'),
                __DIR__.'/../dist/css' => public_path('vendor/guardian/css'),
            ], 'guardian-assets');
        }
    }

    /**
     * Bootstrap any package services.
     */
    public function packageBooted(): void
    {
        // Register routes
        $this->registerRoutes();

        // Register blade directive
        $this->registerBladeDirectives();

        // Register middleware
        $this->app['router']->aliasMiddleware('guardian', GuardianMiddleware::class);

        // Auto-register guardian middleware if enabled
        if (config('guardian.enabled')) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]
                ->prependMiddlewareToGroup('web', GuardianMiddleware::class);
        }

        // Register Gate definition for viewing the dashboard
        $this->registerGates();
    }

    /**
     * Register any package services.
     */
    public function packageRegistered(): void
    {
        // Register main Guardian service
        $this->app->singleton(Guardian::class, function ($app) {
            return new Guardian(
                $app->make(Detection\CrawlerDetector::class),
                $app->make(Prevention\PreventionEngine::class),
                $app->make(Prevention\ContentProtector::class)
            );
        });

        // Register Detection components
        $this->registerDetectionServices();

        // Register Prevention components
        $this->registerPreventionServices();

        // Register Utility components
        $this->registerUtilityServices();

        // Register Protector components
        $this->registerProtectorServices();
    }

    /**
     * Register detection related services.
     */
    protected function registerDetectionServices(): void
    {
        // Register CrawlerDetector
        $this->app->singleton(Detection\CrawlerDetector::class, function ($app) {
            return new Detection\CrawlerDetector(
                $app->make(Detection\Analyzers\HeaderAnalyzer::class),
                $app->make(Detection\Analyzers\RequestPatternAnalyzer::class),
                $app->make(Detection\Analyzers\RateLimitAnalyzer::class),
                $app->make(Detection\Analyzers\BehaviouralAnalyzer::class)
            );
        });

        // Register individual analyzers
        $this->app->singleton(Detection\Analyzers\HeaderAnalyzer::class, function ($app) {
            return new Detection\Analyzers\HeaderAnalyzer;
        });

        $this->app->singleton(Detection\Analyzers\RequestPatternAnalyzer::class, function ($app) {
            return new Detection\Analyzers\RequestPatternAnalyzer;
        });

        $this->app->singleton(Detection\Analyzers\RateLimitAnalyzer::class, function ($app) {
            return new Detection\Analyzers\RateLimitAnalyzer;
        });

        $this->app->singleton(Detection\Analyzers\BehaviouralAnalyzer::class, function ($app) {
            return new Detection\Analyzers\BehaviouralAnalyzer;
        });
    }

    /**
     * Register prevention related services.
     */
    protected function registerPreventionServices(): void
    {
        // Register ContentProtector
        $this->app->singleton(Prevention\ContentProtector::class, function ($app) {
            return new Prevention\ContentProtector;
        });

        // Register HoneypotGenerator
        $this->app->singleton(Prevention\HoneypotGenerator::class, function ($app) {
            return new Prevention\HoneypotGenerator;
        });

        // Register PreventionEngine
        $this->app->singleton(Prevention\PreventionEngine::class, function ($app) {
            return new Prevention\PreventionEngine(
                $app->make(Prevention\ContentProtector::class),
                $app->make(Prevention\HoneypotGenerator::class)
            );
        });
    }

    /**
     * Register utility components.
     */
    protected function registerUtilityServices(): void
    {
        // Register DOM utility
        $this->app->singleton(DomUtility::class, function ($app) {
            return new DomUtility;
        });

        // Register Element preserver
        $this->app->singleton(ElementPreserver::class, function ($app) {
            return new ElementPreserver;
        });
    }

    /**
     * Register protector components.
     */
    protected function registerProtectorServices(): void
    {
        // Register text fragmenter
        $this->app->singleton(TextFragmenter::class, function ($app) {
            return new TextFragmenter;
        });

        // Register CSS obfuscator
        $this->app->singleton(CssObfuscator::class, function ($app) {
            return new CssObfuscator;
        });

        // Register honeytoken injector
        $this->app->singleton(HoneytokenInjector::class, function ($app) {
            return new HoneytokenInjector;
        });

        // Register CSS layers protector
        $this->app->singleton(CssLayersProtector::class, function ($app) {
            return new CssLayersProtector;
        });
    }

    /**
     * Register routes for the package.
     */
    protected function registerRoutes(): void
    {
        // Report endpoint - this is fixed and not configurable
        // Using domain(null) ensures this works regardless of any domain routing configurations
        Route::domain(null)->post(
            '/__guardian__/report',
            [ReportController::class, 'store']
        )->middleware('web')
            ->name('guardian.report')
            ->withoutMiddleware(['throttle', 'csrf']) // Important for reliable reporting
            ->where('guardian_report', '.*'); // Allow any URL format

        // Dashboard routes - using configurable path
        if (config('guardian.dashboard.enabled', true)) {
            $dashboardPath = config('guardian.dashboard.path', '/guardian');
            $dashboardMiddleware = config('guardian.dashboard.middleware', ['web', 'auth']);

            Route::get(
                $dashboardPath,
                [DashboardController::class, 'index']
            )->middleware($dashboardMiddleware)
                ->name('guardian.dashboard');
        }
    }

    /**
     * Register blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // Register the @guardian directive
        Blade::directive('guardian', function () {
            return "<?php echo view('guardian::scripts')->render(); ?>";
        });
    }

    /**
     * Register gate definitions for authorization.
     */
    protected function registerGates(): void
    {
        // Define a gate for viewing the Guardian dashboard
        // By default, only users with the 'admin' role or super-admins can view the dashboard
        // This can be overridden in a service provider by redefining the gate
        Gate::define('viewGuardianDashboard', function ($user) {
            // If the application has a hasRole method (common in many permission packages)
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole('admin') || $user->hasRole('super-admin');
            }

            // If the application uses a simpler is_admin or admin flag
            if (isset($user->is_admin)) {
                return $user->is_admin;
            }

            if (isset($user->admin)) {
                return $user->admin;
            }

            // Default to allowing user ID 1 (typically the first admin)
            return $user->id === 1;
        });
    }
}
