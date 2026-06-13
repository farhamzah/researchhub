<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\ResearchProject;
use App\Models\User;
use App\Modules\Documents\Services\DocumentFileNameSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentFileNameSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggested_academic_file_name_is_deterministic_and_safe(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-13 09:00:00'));

        $owner = User::factory()->create();
        $project = ResearchProject::create([
            'owner_id' => $owner->id,
            'title' => 'Disertasi PharmVR',
            'slug' => 'disertasi-pharmvr',
            'status' => ResearchProject::STATUS_ACTIVE,
        ]);
        $category = DocumentCategory::create([
            'name' => 'BAB III',
            'slug' => 'bab-iii',
        ]);
        $document = Document::create([
            'project_id' => $project->id,
            'category_id' => $category->id,
            'owner_id' => $owner->id,
            'title' => 'BAB III Metodologi Penelitian',
            'document_type' => Document::TYPE_CHAPTER_3,
            'version_label' => 'v02',
            'version_number' => 2,
            'status' => Document::STATUS_REVISION_REQUIRED,
            'visibility' => Document::VISIBILITY_PROJECT,
        ]);

        $suggestion = app(DocumentFileNameSuggestionService::class)->suggest($document);

        $this->assertSame('disertasi-pharmvr_bab-iii_v02_2026-06-13.docx', $suggestion);
        $this->assertStringNotContainsString('\\', $suggestion);
        $this->assertStringNotContainsString('/', $suggestion);
        $this->assertStringNotContainsString('drive', $suggestion);
    }
}
