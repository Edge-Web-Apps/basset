<?php

namespace Backpack\Basset;

use Backpack\Basset\Facades\Basset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Basset Service Provider.
 *
 * @property object $app
 */
class BassetServiceProvider extends ServiceProvider
{
    protected $commands = [
        Console\Commands\BassetCache::class,
        Console\Commands\BassetClear::class,
        Console\Commands\BassetCheck::class,
        Console\Commands\BassetInstall::class,
        Console\Commands\BassetInternalize::class,
        Console\Commands\BassetFresh::class,
    ];

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        // Load basset disk
        $this->loadDisk();

        // Run the terminate commands
        $this->app->terminating(fn () => $this->terminate());
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/config/backpack/basset.php' => config_path('backpack/basset.php'),
        ], 'config');

        // Registering package commands.
        if (! empty($this->commands)) {
            $this->commands($this->commands);
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register the service the package provides.
        $this->app->scoped('basset', fn () => new BassetManager());

        // Merge the configuration file.
        $this->mergeConfigFrom(__DIR__.'/config/backpack/basset.php', 'backpack.basset');

        // Register blade directives
        $this->registerBladeDirectives();
    }

    /**
     * Register Blade Directives.
     *
     * @return void
     */
    protected function registerBladeDirectives()
    {
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {
            // Basset
            return true;
        });
    }

    /**
     * On terminate callback.
     *
     * @return void
     */
    public function terminate(): void
    {
        /** @var BassetManager */
        $basset = app('basset');

        // Log execution time
        if (config('backpack.basset.log_execution_time', false)) {
            $totalCalls = $basset->loader->getTotalCalls();
            $loadingTime = $basset->loader->getLoadingTime();

            Log::info("Basset run $totalCalls times, with an execution time of $loadingTime");
        }

        // Save the cache map
        $basset->cacheMap->save();
    }

    /**
     * Loads needed basset disks.
     *
     * @return void
     */
    public function loadDisk(): void
    {
        // if the basset disk already exists, don't override it
        if (config('filesystems.disks.basset')) {
            return;
        }

        // if the basset disk isn't being used at all, don't even bother to add it
        if (config('backpack.basset.disk') !== 'basset') {
            return;
        }

        // add the basset disk to filesystem configuration
        // should be kept up to date with https://github.com/laravel/laravel/blob/10.x/config/filesystems.php#L39-L45
        config(['filesystems.disks.basset' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ]]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['basset'];
    }
}
