<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Respondent;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySupervisorReviewRound;
use App\Models\User;
use App\Modules\SupervisorReviews\Services\SupervisorReviewSnapshotService;
use App\Modules\Surveys\Actions\InstallPharmVrInstrumentsV2Action;
use App\Modules\Surveys\Services\PharmVrInstrumentV2Catalog;
use App\Modules\Surveys\Services\SurveyAnswerValidationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmVrScientificQaClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_consent_options_in_actual_respondent_and_supervisor_snapshot_are_identical(): void
    {
        [$owner, $surveys] = $this->installed();

        foreach ($surveys as $survey) {
            $question = $survey->questions()->where('question_key', $survey->instrument_code === PharmVrInstrumentV2Catalog::STUDENT ? 'S01-A01' : ($survey->instrument_code === PharmVrInstrumentV2Catalog::LECTURER ? 'S02-A01' : 'S03-A01'))->firstOrFail();
            $this->assertSame(SurveyQuestion::TYPE_SINGLE_CHOICE, $question->type);
            $this->assertSame(['Bersedia', 'Tidak bersedia'], $question->options['choices']);
            $this->assertSame('Tidak bersedia', $question->settings['terminate_on']);
            $this->assertTrue($question->is_required);

            $survey->forceFill(['status' => Survey::STATUS_PUBLISHED, 'is_public' => true, 'published_at' => now()])->save();
            $actual = $this->get(route('survey.show', ['survey' => $survey]));
            $actual->assertOk()
                ->assertSeeText('Saya telah membaca dan memahami informasi penelitian di atas.')
                ->assertSee('name="answers['.$question->question_key.']"', false)
                ->assertSee('value="Bersedia"', false)
                ->assertSee('value="Tidak bersedia"', false);

            if ($survey->instrument_code === PharmVrInstrumentV2Catalog::STUDENT) {
                $actual->assertSee('data-exclusive-choice', false);
            }

            $snapshot = app(SupervisorReviewSnapshotService::class)->snapshot($survey);
            $snapshotQuestion = collect($snapshot['pages'])->flatMap(fn (array $page): array => $page['questions'])->firstWhere('question_key', $question->question_key);
            $this->assertSame($question->options, $snapshotQuestion['options']);
        }
    }

    public function test_refusal_terminates_without_storing_research_entities(): void
    {
        [, $surveys] = $this->installed();
        $survey = $surveys->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::STUDENT);
        $survey->forceFill(['status' => Survey::STATUS_PUBLISHED, 'is_public' => true, 'published_at' => now()])->save();

        $this->post(route('survey.responses.store', ['survey' => $survey]), [
            'intro_consent' => '1',
            'identity' => ['name' => 'Tidak boleh tersimpan', 'email' => 'refusal@example.test'],
            'answers' => ['S01-A01' => 'Tidak bersedia'],
        ])->assertOk()->assertSeeText('Anda memilih tidak berpartisipasi.');

        $this->assertSame(0, Respondent::where('survey_id', $survey->id)->count());
        $this->assertSame(0, SurveyResponse::where('survey_id', $survey->id)->count());
        $this->assertSame(0, SurveyAnswer::whereHas('response', fn ($query) => $query->where('survey_id', $survey->id))->count());
    }

    public function test_exclusive_choice_accepts_single_value_and_rejects_contradiction_without_affecting_normal_multiple_choice(): void
    {
        [, $surveys] = $this->installed();
        $survey = $surveys->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::STUDENT)->load('questions');
        $validator = app(SurveyAnswerValidationService::class);

        $accepted = $validator->validate($survey, [
            'S01-A01' => 'Bersedia',
            'S01-A05' => ['Belum pernah'],
            'S01-B03' => ['Smartphone', 'Laptop/komputer'],
            'S01-B01' => ['Ceramah/diskusi', 'Video'],
        ]);
        $this->assertSame(['Belum pernah'], $accepted['S01-A05']);
        $this->assertSame(['Smartphone', 'Laptop/komputer'], $accepted['S01-B03']);
        $this->assertSame(['Ceramah/diskusi', 'Video'], $accepted['S01-B01']);

        try {
            $validator->validate($survey, ['S01-A01' => 'Bersedia', 'S01-A05' => ['Belum pernah', 'Kunjungan industri']]);
            $this->fail('Contradictory exclusive selection should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.S01-A05', $exception->errors());
        }

        try {
            $validator->validate($survey, ['S01-A01' => 'Bersedia', 'S01-F01' => ['Orientasi CPOB dan fasilitas', 'Higiene/gowning/masuk area', 'Alur personel/material', 'Gudang dan dispensing/penimbangan']]);
            $this->fail('Maximum selection should remain enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers.S01-F01', $exception->errors());
        }
    }

    public function test_page_instructions_and_interviewer_probes_are_persisted_and_rendered(): void
    {
        [$owner, $surveys] = $this->installed();
        $student = $surveys->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::STUDENT);
        $lecturer = $surveys->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::LECTURER);
        $practitioner = $surveys->firstWhere('instrument_code', PharmVrInstrumentV2Catalog::PRACTITIONER);

        $this->assertSame('Berdasarkan pembelajaran yang sudah Anda ikuti, seberapa sulit hal berikut dipahami? Pilih ‘Belum mempelajari’ bila Anda belum pernah mempelajarinya.', $student->pages()->where('title', 'C. Kesulitan Belajar CPOB')->value('description'));
        $this->assertSame('Jawab berdasarkan kondisi institusi yang Anda ketahui saat ini.', $lecturer->pages()->where('title', 'E. Kelayakan Implementasi')->value('description'));
        $this->assertSame('Gali aspek pengetahuan, prosedur, dokumentasi, dan perilaku profesional.', $practitioner->questions()->where('question_key', 'S03-B01')->firstOrFail()->settings['interviewer_probe']);
        $this->assertSame(26, $practitioner->questions()->count());

        $practitioner->forceFill(['status' => Survey::STATUS_PUBLISHED, 'is_public' => true, 'published_at' => now()])->save();
        $this->get(route('survey.show', ['survey' => $practitioner]))
            ->assertOk()
            ->assertSeeText('Pertanyaan utama dapat diperdalam dengan probe; jawaban tetap dicatat sebagai data wawancara, bukan skor Likert.')
            ->assertSeeText('Gali batch record, label/status, deviation, dan QA release bila relevan.');

        $this->actingAs($owner)->post(route('admin.surveys.supervisor-review.rounds.store', ['survey' => $practitioner]), [
            'title' => 'Review S03 v2.0', 'purpose' => 'Scientific QA', 'status' => SurveySupervisorReviewRound::STATUS_OPEN,
        ])->assertRedirect();
        $round = $practitioner->supervisorReviewRounds()->firstOrFail();
        $this->actingAs($owner)->post(route('admin.surveys.supervisor-review.reviewers.store', ['survey' => $practitioner, 'round' => $round]), [
            'supervisor_name' => 'Prof. QA', 'supervisor_email' => 'qa-supervisor@example.test', 'supervisor_code' => 'QA-01', 'role' => 'Promotor',
        ])->assertRedirect();
        $reviewer = $round->reviewers()->firstOrFail();
        $this->actingAs($owner)->post(route('admin.surveys.supervisor-review.reviewers.generate-link', ['survey' => $practitioner, 'reviewer' => $reviewer]))
            ->assertSessionHas('generated_supervisor_review_url');
        $token = basename(parse_url(session('generated_supervisor_review_url'), PHP_URL_PATH));
        $this->get(route('supervisor-review.survey.show', ['token' => $token]))
            ->assertOk()
            ->assertSeeText('Pertanyaan utama dapat diperdalam dengan probe; jawaban tetap dicatat sebagai data wawancara, bukan skor Likert.')
            ->assertSeeText('Gali urutan tindakan, critical step, dokumentasi, dan pengambilan keputusan.');
    }

    /** @return array{User, Collection<int, Survey>} */
    private function installed(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $owner = User::factory()->create(['email' => 'researcher@example.test']);
        $owner->assignRole('admin');
        $project = ResearchProject::create(['owner_id' => $owner->id, 'title' => 'Riset PharmVR QA', 'slug' => 'pharmvr-scientific-qa', 'status' => ResearchProject::STATUS_ACTIVE]);
        $surveys = collect(app(InstallPharmVrInstrumentsV2Action::class)->handle($owner, $project, 'researcher@example.test'))->pluck('survey');

        return [$owner, $surveys];
    }
}
