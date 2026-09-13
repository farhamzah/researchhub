<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveySupervisorReviewer;
use App\Models\SurveySupervisorReviewerHub;
use App\Models\SurveySupervisorReviewRound;
use App\Models\User;
use App\Modules\SupervisorReviews\Actions\CreateSupervisorReviewerHubAction;
use App\Modules\SupervisorReviews\Services\SupervisorReviewSnapshotService;
use App\Modules\Surveys\Actions\FinalizePharmVrInstrumentsV2ForSupervisorReviewAction;
use App\Modules\Surveys\Actions\InstallPharmVrInstrumentsV2Action;
use App\Modules\Surveys\Services\PharmVrInstrumentV2Catalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PharmVrFinalSupervisorReviewPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_only_wording_replaces_unopened_snapshots_and_rotates_three_hub_links(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'PharmVR Final Review',
            'slug' => 'pharmvr-final-review',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $surveys = collect(app(InstallPharmVrInstrumentsV2Action::class)->handle($owner, $project, $owner->email))->pluck('survey');

        $surveys->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::STUDENT)
            ->questions()->where('question_key', 'S01-F01')->update(['label' => 'Pilih maksimal 3 scene prioritas:']);
        $lecturer = $surveys->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::LECTURER);
        $lecturer->questions()->where('question_key', 'S02-F01')->update(['label' => 'Pilih maksimal 3 scene prioritas:']);
        $lecturer->questions()->where('question_key', 'S02-C07')->update(['label' => 'Media realistis:']);
        $surveys->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::PRACTITIONER)
            ->questions()->where('question_key', 'S03-B01')->update(['label' => 'Kompetensi CPOB paling penting bagi mahasiswa/lulusan baru.']);
        $s02Options = $lecturer->questions()->where('question_key', 'S02-C07')->firstOrFail()->options;

        $supervisors = [
            ['code' => 'P1', 'name' => 'Pembimbing Satu'],
            ['code' => 'P2', 'name' => 'Pembimbing Dua'],
            ['code' => 'P3', 'name' => 'Pembimbing Tiga'],
        ];
        $snapshotService = app(SupervisorReviewSnapshotService::class);
        $oldSnapshotHashes = [];

        foreach ($surveys as $survey) {
            $round = SurveySupervisorReviewRound::create([
                'survey_id' => $survey->id,
                'research_project_id' => $project->id,
                'created_by' => $owner->id,
                'title' => 'Review final '.$survey->instrument_code,
                'instrument_version' => '2.0',
                'status' => SurveySupervisorReviewRound::STATUS_OPEN,
                'opened_at' => now(),
            ]);
            $snapshotService->ensureSnapshot($round);
            $oldSnapshotHashes[$survey->instrument_code] = $round->fresh()->snapshot_hash;
            foreach ($supervisors as $supervisor) {
                $round->reviewers()->create([
                    'supervisor_name' => $supervisor['name'],
                    'supervisor_code' => $supervisor['code'],
                    'status' => SurveySupervisorReviewer::STATUS_NOT_OPENED,
                    'created_by' => $owner->id,
                ]);
            }
        }

        $reviewers = SurveySupervisorReviewer::query()->with('round.survey')->get();
        $oldTokens = [];
        foreach ($reviewers->groupBy('supervisor_code') as $group) {
            $created = app(CreateSupervisorReviewerHubAction::class)->handle($owner, $project, $group, now()->addDays(30));
            $oldTokens[] = $created->rawToken;
        }
        $oldHubIds = SurveySupervisorReviewerHub::pluck('id');
        $oldTokenHashes = SurveySupervisorReviewerHub::pluck('token_hash')->all();
        $outputPath = storage_path('app/private/test-pharmvr-final-'.Str::uuid().'.json');

        try {
            $result = app(FinalizePharmVrInstrumentsV2ForSupervisorReviewAction::class)->handle(
                $owner,
                $project,
                $owner->email,
                now()->addDays(30),
                $outputPath,
            );

            $this->assertSame([34, 35, 26], array_values($result['questions']));
            $this->assertSame(3, $result['old_hubs_revoked']);
            $this->assertSame(3, $result['new_hubs_created']);
            $this->assertFileExists($outputPath);
            $this->assertSame(hash_file('sha256', $outputPath), $result['sha256']);
            $this->assertArrayNotHasKey('links', $result);
            $this->assertCount(3, json_decode(file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR)['links']);

            $this->assertSame(3, SurveySupervisorReviewerHub::count());
            $this->assertSame(3, SurveySupervisorReviewerHub::whereNull('revoked_at')->count());
            $this->assertEqualsCanonicalizing($oldHubIds->all(), SurveySupervisorReviewerHub::pluck('id')->all());
            foreach ($oldTokenHashes as $oldHash) {
                $this->assertFalse(SurveySupervisorReviewerHub::where('token_hash', $oldHash)->exists());
            }
            foreach ($oldTokens as $oldToken) {
                $this->get(route('supervisor-review.hub.show', ['token' => $oldToken]))->assertNotFound();
            }
            $this->assertSame(9, SurveySupervisorReviewer::where('status', SurveySupervisorReviewer::STATUS_NOT_OPENED)->whereNull('opened_at')->count());

            $refreshed = Survey::query()->whereIn('id', $surveys->pluck('id'))->with('supervisorReviewRounds')->get();
            foreach ($refreshed as $survey) {
                $this->assertSame(Survey::STATUS_DRAFT, $survey->status);
                $this->assertFalse($survey->is_public);
                $this->assertSame(0, $survey->responses()->count());
                $this->assertNotSame($oldSnapshotHashes[$survey->instrument_code], $survey->supervisorReviewRounds->sole()->snapshot_hash);
            }

            $this->assertSame('Pilih maksimal 3 area/skenario pembelajaran prioritas:', $refreshed->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::STUDENT)->questions()->where('question_key', 'S01-F01')->value('label'));
            $this->assertSame('Media atau metode pembelajaran apa yang menurut Bapak/Ibu paling realistis diterapkan di institusi untuk membantu mengatasi kesenjangan tersebut?', $refreshed->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::LECTURER)->questions()->where('question_key', 'S02-C07')->value('label'));
            $this->assertSame($s02Options, $refreshed->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::LECTURER)->questions()->where('question_key', 'S02-C07')->firstOrFail()->options);
            $this->assertSame('Menurut Bapak/Ibu, kompetensi CPOB apa yang paling penting dimiliki mahasiswa atau lulusan baru sebelum memasuki industri farmasi?', $refreshed->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::PRACTITIONER)->questions()->where('question_key', 'S03-B01')->value('label'));
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }
}
