<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\ResearchProject;
use App\Policies\DocumentPolicy;
use App\Policies\ResearchProjectPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ResearchProject::class, ResearchProjectPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);

        RateLimiter::for('review-links', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip() ?: 'anonymous');
        });

        RateLimiter::for('review-link-passwords', function (Request $request): Limit {
            return Limit::perMinute(5)->by(($request->ip() ?: 'anonymous').'|'.(string) $request->route('token'));
        });
    }
}
