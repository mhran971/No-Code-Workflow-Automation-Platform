<?php

namespace Modules\Workflows\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Workflows\Services\Execution\ExecutionPlanCompiler;
use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
use Modules\Workflows\Services\Execution\NodeExecutorRegistry;
use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
use Modules\Workflows\Services\Verification\Rules\ContextualVerificationRule;
use Modules\Workflows\Services\Verification\Rules\ExpressionVerificationRule;
use Modules\Workflows\Services\Verification\Rules\GraphControlFlowVerificationRule;
use Modules\Workflows\Services\Verification\Rules\SyntaxVerificationRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionNormalizer;
use Modules\Workflows\Services\WorkflowDefinitionValidator;
use Modules\Workflows\Services\WorkflowManagementService;
use Modules\Workflows\Services\WorkflowVerificationService;

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

        $this->app->singleton(WorkflowDefinitionNormalizer::class);
        $this->app->singleton(ExpressionLanguageValidator::class);
        $this->app->singleton(SyntaxVerificationRule::class);
        $this->app->singleton(GraphControlFlowVerificationRule::class);
        $this->app->singleton(ExpressionVerificationRule::class);
        $this->app->singleton(ContextualVerificationRule::class);
        $this->app->singleton(WorkflowVerificationService::class);
        $this->app->singleton(WorkflowDefinitionValidator::class);
        $this->app->singleton(WorkflowManagementService::class);

        // Execution engine (M0 foundations).
        $this->app->singleton(ExpressionEvaluator::class);
        $this->app->singleton(TemplateInterpolator::class);
        $this->app->singleton(ExecutionPlanCompiler::class);
        $this->app->singleton(NodeExecutorRegistry::class);
    }
}
