<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Policies\DocumentPolicy;
use App\Policies\ResearchProjectPolicy;
use App\Policies\SurveyPolicy;
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
        Gate::policy(Survey::class, SurveyPolicy::class);

        RateLimiter::for('review-links', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip() ?: 'anonymous');
        });

        RateLimiter::for('review-link-passwords', function (Request $request): Limit {
            return Limit::perMinute(5)->by(($request->ip() ?: 'anonymous').'|'.(string) $request->route('token'));
        });

        RateLimiter::for('surveys', function (Request $request): Limit {
            $routeSurvey = $request->route('survey');
            $surveyKey = $routeSurvey instanceof Survey ? $routeSurvey->getRouteKey() : (string) $routeSurvey;

            return Limit::perMinute(20)->by(($request->ip() ?: 'anonymous').'|'.$surveyKey);
        });
    }
}
