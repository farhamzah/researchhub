<?php

namespace Tests\Feature;

use App\Models\AnalysisPilotRun;
use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Modules\Analysis\Services\AnalysisCollectionMonitoringService;
use App\Modules\Analysis\Services\AnalysisDocumentPackageService;
use App\Modules\Surveys\Actions\CreateLecturerNeedsAnalysisQuestionnaireAction;
use App\Modules\Surveys\Actions\CreatePractitionerInterviewFormAction;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use App\Modules\Surveys\Services\SurveyDistributionCenterService;
use App\Modules\Surveys\Support\SurveyIntroTemplates;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RespondentPackagePilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_package_and_see_final_real_links(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture(withRelated: true);

        $this->actingAs($admin)
            ->get(route('admin.surveys.respondent-package.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee('Final Respondent Link Package & Pilot Test', false)
            ->assertSeeText('Final Real Links')
            ->assertSeeText('Student Questionnaire')
            ->assertSeeText('Lecturer Questionnaire')
            ->assertSeeText('Practitioner Interview Form')
            ->assertSee(route('survey.show', ['survey' => $survey->slug]))
            ->assertDontSee('token_hash');
    }

    public function test_package_routes_are_authenticated_and_project_authorized(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $other = User::factory()->create();
        $other->assignRole('admin');

        $this->get(route('admin.surveys.respondent-package.index', ['survey' => $survey]))
            ->assertRedirect('/admin/login');

        $this->actingAs($other)
            ->get(route('admin.surveys.respondent-package.index', ['survey' => $survey]))
            ->assertForbidden();
    }

    public function test_admin_can_generate_hash_only_pilot_link_and_public_banner_displays(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();

        $response = $this->actingAs($admin)
            ->post(route('admin.surveys.respondent-package.pilot.generate', [
                'survey' => $survey,
                'audience' => AnalysisPilotRun::AUDIENCE_STUDENT,
            ]))
            ->assertRedirect(route('admin.surveys.respondent-package.index', ['survey' => $survey]));

        $url = $response->baseResponse->getSession()->get('generated_pilot_url');
        $run = AnalysisPilotRun::firstOrFail();

        $this->assertNotNull($url);
        $this->assertNotNull($run->token_hash);
        $this->assertStringNotContainsString((string) $run->token_hash, $url);
        $this->assertStringContainsString('pilot=', $url);

        $this->get($url)
            ->assertOk()
            ->assertSeeText('MODE UJI COBA / REVIEWER')
            ->assertSeeText('tidak masuk hasil analisis');

        $this->assertTrue($run->fresh()->isActive());
    }

    public function test_valid_pilot_link_opens_full_flow_when_public_survey_is_closed(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $url = $this->generatePilotUrl($admin, $survey);
        $this->closePublicAccess($survey);

        $this->get(route('survey.show', ['survey' => $survey->fresh()->slug]))
            ->assertOk()
            ->assertSeeText('Survey tidak tersedia')
            ->assertDontSeeText($survey->title);

        $this->get($url)
            ->assertOk()
            ->assertSeeText('MODE UJI COBA / REVIEWER')
            ->assertSeeText('Pengantar Kuesioner Analisis Kebutuhan PharmVR')
            ->assertSeeText('Data yang dikumpulkan')
            ->assertSeeText('Saya telah membaca penjelasan di atas dan bersedia melanjutkan.')
            ->assertSeeText('Mahasiswa membutuhkan PharmVR untuk memahami CPOB.')
            ->assertSee('name="pilot"', false)
            ->assertSee('name="intro_consent"', false)
            ->assertSee('name="answers[student_need]"', false);

        $this->assertTrue(AnalysisPilotRun::firstOrFail()->fresh()->isActive());
    }

    public function test_invalid_and_revoked_pilot_links_do_not_bypass_closed_public_access(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $url = $this->generatePilotUrl($admin, $survey);
        $token = $this->tokenFromUrl($url);
        $run = AnalysisPilotRun::firstOrFail();
        $this->closePublicAccess($survey);

        $this->get(route('survey.show', ['survey' => $survey->fresh()->slug, 'pilot' => 'invalid-token']))
            ->assertForbidden()
            ->assertSeeText('Link uji coba tidak aktif.');

        $this->actingAs($admin)
            ->post(route('admin.surveys.respondent-package.pilot.revoke', ['survey' => $survey, 'pilotRun' => $run]))
            ->assertRedirect(route('admin.surveys.respondent-package.index', ['survey' => $survey]));

        $this->get(route('survey.show', ['survey' => $survey->fresh()->slug, 'pilot' => $token]))
            ->assertForbidden()
            ->assertSeeText('Link uji coba tidak aktif.');
    }

    public function test_closed_survey_pilot_submission_is_stored_as_excluded_test_data(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $token = $this->tokenFromUrl($this->generatePilotUrl($admin, $survey));
        $this->closePublicAccess($survey);

        $this->post(route('survey.responses.store', ['survey' => $survey->fresh()->slug]), [
            'pilot' => $token,
            'intro_consent' => '1',
            'answers' => ['student_need' => '5'],
        ])
            ->assertOk()
            ->assertSeeText('Respons uji coba disimpan sebagai data tes');

        $response = SurveyResponse::firstOrFail();

        $this->assertTrue($response->is_test_response);
        $this->assertTrue($response->excluded_from_analysis);
        $this->assertNotNull($response->pilot_run_id);
        $this->assertSame(AnalysisPilotRun::STATUS_ACTIVE, AnalysisPilotRun::firstOrFail()->status);
        $this->assertTrue(AnalysisPilotRun::firstOrFail()->isActive());
    }

    public function test_pilot_submission_is_stored_as_excluded_test_data_and_updates_run(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $url = $this->generatePilotUrl($admin, $survey);
        $token = $this->tokenFromUrl($url);

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'pilot' => $token,
            'intro_consent' => '1',
            'answers' => ['student_need' => '5'],
        ])
            ->assertOk()
            ->assertSeeText('Respons uji coba disimpan sebagai data tes');

        $response = SurveyResponse::firstOrFail();
        $run = AnalysisPilotRun::firstOrFail();

        $this->assertTrue($response->is_test_response);
        $this->assertTrue($response->excluded_from_analysis);
        $this->assertSame($run->id, $response->pilot_run_id);
        $this->assertSame(AnalysisPilotRun::STATUS_ACTIVE, $run->fresh()->status);
        $this->assertTrue($run->fresh()->isActive());
        $this->assertNotNull($run->fresh()->submitted_at);
    }

    public function test_same_pilot_link_can_submit_multiple_excluded_responses(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $token = $this->tokenFromUrl($this->generatePilotUrl($admin, $survey));
        $this->closePublicAccess($survey);

        foreach (['5', '4', '3'] as $answer) {
            $this->post(route('survey.responses.store', ['survey' => $survey->fresh()->slug]), [
                'pilot' => $token,
                'intro_consent' => '1',
                'answers' => ['student_need' => $answer],
            ])
                ->assertOk()
                ->assertSeeText('Respons uji coba disimpan sebagai data tes');
        }

        $run = AnalysisPilotRun::firstOrFail();

        $this->assertSame(3, SurveyResponse::count());
        $this->assertSame(3, SurveyResponse::testData()->count());
        $this->assertSame(AnalysisPilotRun::STATUS_ACTIVE, $run->fresh()->status);
        $this->assertTrue($run->fresh()->isActive());
        $this->assertSame(3, $run->responses()->count());
    }

    public function test_analysis_services_exclude_test_responses_from_normal_counts(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $this->submitPilotResponse($admin, $survey);
        $this->submitRealResponse($survey);

        $collection = app(AnalysisCollectionMonitoringService::class)->build($survey->fresh(), $admin);
        $distribution = app(SurveyDistributionCenterService::class)->build($survey->fresh(), $admin);
        $package = app(AnalysisDocumentPackageService::class)->build($survey->fresh(), $admin);

        $studentSource = collect($collection['sources'])->firstWhere('source_type', 'student_questionnaire');
        $studentPanel = collect($distribution['instruments'])->firstWhere('audience', 'student');

        $this->assertSame(1, $studentSource['current_count']);
        $this->assertSame(1, $studentPanel['response_count']);
        $this->assertSame(1, $package['instruments']['student']['response_count']);
    }

    public function test_test_responses_can_be_cleared_without_deleting_real_responses(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $this->submitPilotResponse($admin, $survey);
        $this->submitRealResponse($survey);

        $this->assertSame(2, SurveyResponse::count());

        $this->actingAs($admin)
            ->post(route('admin.surveys.respondent-package.test-responses.clear-target', [
                'survey' => $survey,
                'targetSurvey' => $survey,
            ]))
            ->assertRedirect(route('admin.surveys.respondent-package.index', ['survey' => $survey]));

        $this->assertSame(1, SurveyResponse::count());
        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id,
            'is_test_response' => false,
            'excluded_from_analysis' => false,
        ]);
    }

    public function test_pilot_checklist_marks_run_passed_only_when_required_items_are_true(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $this->generatePilotUrl($admin, $survey);
        $run = AnalysisPilotRun::firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.surveys.respondent-package.pilot.checklist', ['survey' => $survey, 'pilotRun' => $run]), [
                'intro_ok' => '1',
                'notes' => 'Intro checked only.',
            ])
            ->assertRedirect(route('admin.surveys.respondent-package.index', ['survey' => $survey]));

        $this->assertNotSame(AnalysisPilotRun::STATUS_PASSED, $run->fresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.surveys.respondent-package.pilot.checklist', ['survey' => $survey, 'pilotRun' => $run]), [
                'intro_ok' => '1',
                'consent_ok' => '1',
                'questions_ok' => '1',
                'required_validation_ok' => '1',
                'submit_ok' => '1',
                'thank_you_ok' => '1',
                'excluded_from_analysis_ok' => '1',
                'mobile_view_ok' => '1',
                'desktop_view_ok' => '1',
                'notes' => 'Pilot passed.',
            ])
            ->assertRedirect(route('admin.surveys.respondent-package.index', ['survey' => $survey]));

        $this->assertSame(AnalysisPilotRun::STATUS_PASSED, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->passed_at);
    }

    public function test_revoked_pilot_link_cannot_submit_and_normal_submission_still_stores_real_response(): void
    {
        [$admin, $survey] = $this->publishedAnalysisFixture();
        $url = $this->generatePilotUrl($admin, $survey);
        $token = $this->tokenFromUrl($url);
        $run = AnalysisPilotRun::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.surveys.respondent-package.pilot.revoke', ['survey' => $survey, 'pilotRun' => $run]))
            ->assertRedirect(route('admin.surveys.respondent-package.index', ['survey' => $survey]));

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'pilot' => $token,
            'intro_consent' => '1',
            'answers' => ['student_need' => '5'],
        ])
            ->assertForbidden()
            ->assertSeeText('Link uji coba tidak aktif.');

        $this->assertSame(0, SurveyResponse::count());

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'intro_consent' => '1',
            'answers' => ['student_need' => '4'],
        ])
            ->assertOk();

        $real = SurveyResponse::firstOrFail();
        $this->assertFalse($real->is_test_response);
        $this->assertFalse($real->excluded_from_analysis);
        $this->assertNull($real->pilot_run_id);
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function publishedAnalysisFixture(bool $withRelated = false): array
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create(['name' => 'Researcher Admin']);
        $admin->assignRole('admin');

        $project = ResearchProject::create([
            'owner_id' => $admin->id,
            'title' => 'PharmVR ADDIE Research',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);

        $survey = app(CreateSurveyAction::class)->handle($admin, $project, [
            'title' => 'Analisis Kebutuhan Mahasiswa PharmVR',
            'description' => 'Student needs analysis questionnaire for PharmVR.',
            'identity_mode' => Survey::IDENTITY_ANONYMOUS,
            'instrument_type' => Survey::INSTRUMENT_ANALYSIS_STUDENT,
            'analysis_group_key' => Survey::ANALYSIS_GROUP_PHARMVR_ADDIE,
            ...SurveyIntroTemplates::studentPharmVr(),
        ]);

        $page = $survey->pages()->create([
            'title' => 'Kebutuhan Pembelajaran',
            'description' => 'Bagian kebutuhan mahasiswa.',
            'sort_order' => 1,
        ]);

        $survey->questions()->create([
            'page_id' => $page->id,
            'question_key' => 'student_need',
            'type' => SurveyQuestion::TYPE_LIKERT,
            'label' => 'Mahasiswa membutuhkan PharmVR untuk memahami CPOB.',
            'options' => ['scale' => [1, 2, 3, 4, 5]],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        app(PublishSurveyAction::class)->handle($admin, $survey->fresh());

        if ($withRelated) {
            $lecturer = app(CreateLecturerNeedsAnalysisQuestionnaireAction::class)->handle($admin, $survey->fresh());
            $practitioner = app(CreatePractitionerInterviewFormAction::class)->handle($admin, $survey->fresh());
            app(PublishSurveyAction::class)->handle($admin, $lecturer->fresh());
            app(PublishSurveyAction::class)->handle($admin, $practitioner->fresh());
        }

        return [$admin, $survey->fresh()];
    }

    private function generatePilotUrl(User $admin, Survey $survey): string
    {
        $response = $this->actingAs($admin)
            ->post(route('admin.surveys.respondent-package.pilot.generate', [
                'survey' => $survey,
                'audience' => AnalysisPilotRun::AUDIENCE_STUDENT,
            ]));

        return (string) $response->baseResponse->getSession()->get('generated_pilot_url');
    }

    private function submitPilotResponse(User $admin, Survey $survey): void
    {
        $token = $this->tokenFromUrl($this->generatePilotUrl($admin, $survey));

        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'pilot' => $token,
            'intro_consent' => '1',
            'answers' => ['student_need' => '5'],
        ])->assertOk();
    }

    private function submitRealResponse(Survey $survey): void
    {
        $this->post(route('survey.responses.store', ['survey' => $survey->slug]), [
            'intro_consent' => '1',
            'answers' => ['student_need' => '4'],
        ])->assertOk();
    }

    private function closePublicAccess(Survey $survey): void
    {
        $survey->forceFill([
            'status' => Survey::STATUS_DRAFT,
            'is_public' => false,
            'published_at' => null,
        ])->save();
    }

    private function tokenFromUrl(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) $query['pilot'];
    }
}
