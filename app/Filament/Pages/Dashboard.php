<?php

namespace App\Filament\Pages;

use App\Models\AnalysisResult;
use App\Models\Document;
use App\Models\DriveConnection;
use App\Models\ResearchProject;
use App\Models\ReviewLink;
use App\Models\Survey;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Dashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static string|UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = '';

    protected static ?string $title = 'Dashboard';

    protected string $view = 'filament.pages.dashboard';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [
                'projectCount' => 0,
                'documentCount' => 0,
                'surveyCount' => 0,
                'analysisResultCount' => 0,
                'activeReviewCount' => 0,
                'driveConnected' => false,
            ];
        }

        // All counts use the same visibility scopes enforced by Filament resources —
        // no policy bypass, no cross-user data exposure.
        $projectCount = ResearchProject::query()
            ->visibleTo($user)
            ->count();

        $documentCount = Document::query()
            ->visibleTo($user)
            ->count();

        $surveyCount = Survey::query()
            ->visibleTo($user)
            ->count();

        $analysisResultCount = AnalysisResult::query()
            ->whereHas('survey', fn ($q) => $q->visibleTo($user))
            ->count();

        $activeReviewCount = ReviewLink::query()
            ->whereHas('project', fn ($q) => $q->visibleTo($user))
            ->where('status', ReviewLink::STATUS_ACTIVE)
            ->count();

        $driveConnected = $user->googleDriveConnection()
            ->where('status', DriveConnection::STATUS_CONNECTED)
            ->exists();

        return [
            'projectCount' => $projectCount,
            'documentCount' => $documentCount,
            'surveyCount' => $surveyCount,
            'analysisResultCount' => $analysisResultCount,
            'activeReviewCount' => $activeReviewCount,
            'driveConnected' => $driveConnected,
        ];
    }
}
