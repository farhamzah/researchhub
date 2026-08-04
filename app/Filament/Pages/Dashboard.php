<?php

namespace App\Filament\Pages;

use App\Modules\Dashboard\Services\DashboardDataService;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static string|UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -100;

    protected static ?string $title = 'Dashboard';

    protected string $view = 'filament.pages.dashboard';

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [
                'stats' => [],
                'driveStatus' => [
                    'connected' => false,
                    'label' => 'Belum terhubung',
                    'description' => 'Masuk ke akun untuk melihat status Google Drive.',
                ],
                'activeProjects' => collect(),
                'journeyProjects' => collect(),
                'onboardingChecklist' => [],
                'timelineSummary' => [
                    'active_tasks' => 0,
                    'delayed_tasks' => 0,
                    'upcoming_tasks' => 0,
                    'next_due_task' => null,
                ],
                'recentDocuments' => collect(),
                'recentSurveys' => collect(),
                'recentAnalysisResults' => collect(),
                'pinnedResearchLinks' => collect(),
                'actionCenterItems' => collect(),
                'pendingFollowUps' => collect(),
                'validationPending' => collect(),
                'recentSupervisionFeedback' => collect(),
                'timelineRisks' => collect(),
                'quickActions' => [],
            ];
        }

        return app(DashboardDataService::class)->build($user);
    }
}
