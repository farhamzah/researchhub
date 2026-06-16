<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\Survey;
use App\Models\User;
use App\Modules\Surveys\Actions\CreateSurveyAction;
use App\Modules\Surveys\Actions\PublishSurveyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SurveyIntroImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_intro_image_and_public_intro_displays_it(): void
    {
        Storage::fake('public');
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
                'intro_title' => 'Pengantar Survey',
                'intro_text' => 'Survey ini menjelaskan konteks penelitian sebelum pertanyaan.',
                'estimated_duration' => '10-15 menit',
                'privacy_statement' => 'Data responden dijaga rahasia.',
                'respondent_instruction' => 'Baca instruksi sebelum menjawab.',
                'consent_text' => 'Saya bersedia melanjutkan.',
                'require_consent_before_start' => '1',
                'intro_image' => UploadedFile::fake()->image('intro.jpg', 1200, 675)->size(512),
                'intro_image_alt_text' => 'Ilustrasi responden membaca pengantar survey penelitian',
                'intro_image_caption' => 'Gambar bersifat ilustratif dan tidak memengaruhi jawaban responden.',
                'intro_image_source_note' => 'Sumber: Dokumentasi peneliti.',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $survey->refresh();

        $this->assertStringStartsWith("surveys/{$survey->id}/intro/intro-illustration-", $survey->intro_image_path);
        Storage::disk('public')->assertExists($survey->intro_image_path);
        $this->assertStringContainsString('/storage/surveys/', $survey->intro_image_url);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSee($survey->intro_image_url, false)
            ->assertSee('Ilustrasi responden membaca pengantar survey penelitian');

        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false)
            ->assertSee($survey->intro_image_url, false)
            ->assertSee('Ilustrasi responden membaca pengantar survey penelitian')
            ->assertSeeText('Gambar bersifat ilustratif dan tidak memengaruhi jawaban responden.')
            ->assertSeeText('Sumber: Dokumentasi peneliti.')
            ->assertDontSee('Private Intro Image Project');
    }

    public function test_admin_can_update_intro_image_alt_and_caption_without_replacing_file(): void
    {
        Storage::fake('public');
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
            'intro_text' => 'Initial intro.',
            'intro_image' => UploadedFile::fake()->image('intro.png', 1200, 675)->size(256),
            'intro_image_alt_text' => 'Initial alt text',
            'intro_image_caption' => 'Initial caption',
        ]);

        $originalPath = $survey->refresh()->intro_image_path;

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
                'intro_text' => 'Updated intro.',
                'intro_image_alt_text' => 'Updated alt text',
                'intro_image_caption' => 'Updated caption',
                'intro_image_source_note' => 'Updated source note',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $survey->refresh();

        $this->assertSame($originalPath, $survey->intro_image_path);
        $this->assertSame('Updated alt text', $survey->intro_image_alt_text);
        $this->assertSame('Updated caption', $survey->intro_image_caption);
        $this->assertSame('Updated source note', $survey->intro_image_source_note);
        Storage::disk('public')->assertExists($originalPath);
    }

    public function test_admin_can_remove_intro_image_and_public_intro_omits_figure(): void
    {
        Storage::fake('public');
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
            'intro_text' => 'Intro with image.',
            'intro_image' => UploadedFile::fake()->image('intro.webp', 1200, 675)->size(256),
            'intro_image_alt_text' => 'Removable intro image',
            'intro_image_caption' => 'Caption that should be removed.',
        ]);

        $originalPath = $survey->refresh()->intro_image_path;
        Storage::disk('public')->assertExists($originalPath);

        $this->actingAs($owner)
            ->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
                'intro_text' => 'Intro without image.',
                'remove_intro_image' => '1',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]));

        $survey->refresh();

        $this->assertNull($survey->intro_image_path);
        $this->assertNull($survey->intro_image_alt_text);
        $this->assertNull($survey->intro_image_caption);
        Storage::disk('public')->assertMissing($originalPath);

        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSeeText('Intro without image.')
            ->assertDontSee('Removable intro image')
            ->assertDontSee('<figure', false);
    }

    public function test_public_intro_without_image_still_renders_normally(): void
    {
        [$owner, $survey] = $this->surveyFixture();
        $survey->forceFill([
            'intro_title' => 'Pengantar Tanpa Gambar',
            'intro_text' => 'Intro biasa tanpa ilustrasi.',
            'estimated_duration' => '5 menit',
        ])->save();

        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSeeText('Pengantar Tanpa Gambar')
            ->assertSeeText('5 menit')
            ->assertDontSee('<figure', false)
            ->assertDontSee('No intro image uploaded');
    }

    public function test_missing_intro_image_file_does_not_render_broken_image_tag(): void
    {
        Storage::fake('public');
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
            'intro_text' => 'Intro with missing storage file.',
            'intro_image' => UploadedFile::fake()->image('intro.png', 1200, 675)->size(256),
            'intro_image_alt_text' => 'Missing intro image',
        ]);

        $survey->refresh();
        Storage::disk('public')->assertExists($survey->intro_image_path);
        Storage::disk('public')->delete($survey->intro_image_path);

        $survey->refresh();

        $this->assertNull($survey->intro_image_url);

        $this->actingAs($owner)
            ->get(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertOk()
            ->assertSeeText('Intro image path is saved, but the file could not be found in public storage.')
            ->assertDontSee('src="/storage/surveys/', false);

        app(PublishSurveyAction::class)->handle($owner, $survey);

        $this->get(route('survey.show', ['survey' => $survey->slug]))
            ->assertOk()
            ->assertSeeText('Intro with missing storage file.')
            ->assertDontSee('Missing intro image')
            ->assertDontSee('src="/storage/surveys/', false);
    }

    public function test_intro_image_upload_requires_valid_image_size_and_alt_text(): void
    {
        Storage::fake('public');
        [$owner, $survey] = $this->surveyFixture();

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
                'intro_text' => 'Intro needs alt text.',
                'intro_image' => UploadedFile::fake()->image('intro.jpg')->size(256),
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('intro_image_alt_text');

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
                'intro_text' => 'Intro rejects PDF.',
                'intro_image' => UploadedFile::fake()->create('intro.pdf', 64, 'application/pdf'),
                'intro_image_alt_text' => 'Invalid non image file',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('intro_image');

        $this->actingAs($owner)
            ->from(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->put(route('admin.surveys.builder.intro.update', ['survey' => $survey]), [
                'intro_text' => 'Intro rejects oversized image.',
                'intro_image' => UploadedFile::fake()->image('large.jpg')->size(2049),
                'intro_image_alt_text' => 'Oversized image',
            ])
            ->assertRedirect(route('admin.surveys.builder.index', ['survey' => $survey]))
            ->assertSessionHasErrors('intro_image');
    }

    /**
     * @return array{0: User, 1: Survey}
     */
    private function surveyFixture(): array
    {
        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Private Intro Image Project',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $survey = app(CreateSurveyAction::class)->handle($owner, $project, [
            'title' => 'Intro Image Survey',
            'description' => 'Survey description.',
            'identity_mode' => Survey::IDENTITY_HIDDEN,
        ]);

        return [$owner, $survey];
    }
}
