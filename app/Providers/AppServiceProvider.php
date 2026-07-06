<?php

namespace App\Providers;

use App\Models\ApprovalWorkflow;
use App\Models\InspectionOrder;
use App\Policies\ApprovalPolicy;
use App\Policies\InspectionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind ApprovalService as singleton
        $this->app->singleton(\App\Services\ApprovalService::class);
    }

    public function boot(): void
    {
        // Register policies
        Gate::policy(InspectionOrder::class, InspectionPolicy::class);
        Gate::policy(ApprovalWorkflow::class, ApprovalPolicy::class);
    }
}