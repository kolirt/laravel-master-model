<?php

namespace Kolirt\MasterModel;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{

    protected array $commands = [
        Commands\InstallCommand::class,
        Commands\PublishConfigConsoleCommand::class,
    ];

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/master-model.php', 'master-model');

        $this->publishFiles();
    }

    public function register(): void
    {
        $this->commands($this->commands);
    }

    private function publishFiles(): void
    {
        $this->publishes([
            __DIR__ . '/../config/master-model.php' => config_path('master-model.php')
        ], 'config');
    }

}
