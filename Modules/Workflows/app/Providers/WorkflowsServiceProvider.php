<?php

namespace Modules\Workflows\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Workflows\Services\WorkflowDefinitionValidator;
use Modules\Workflows\Services\WorkflowManagementService;

class WorkflowsServiceProvider extends ServiceProvider
{
    protected string $name = 'Workflows';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(WorkflowDefinitionValidator::class);
        $this->app->singleton(WorkflowManagementService::class);
    }
}
