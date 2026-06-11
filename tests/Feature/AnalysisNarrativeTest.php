<?php

namespace Tests\Feature;

use App\Modules\Analysis\Services\AcademicNarrativeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisNarrativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_narrative_is_indonesian_draft_and_avoids_inferential_claims(): void
    {
        $analysis = [
            'summary' => [
                'submitted_count' => 42,
                'response_count' => 45,
            ],
            'questions' => [
                [
                    'type' => 'likert',
                    'label' => 'kemudahan penggunaan',
                    'mean' => 4.21,
                ],
                [
                    'type' => 'single_choice',
                    'label' => 'program studi',
                    'frequencies' => [
                        ['value' => 'Pendidikan', 'count' => 30, 'percentage' => 71.43],
                        ['value' => 'Non-pendidikan', 'count' => 12, 'percentage' => 28.57],
                    ],
                ],
            ],
        ];

        $narrative = app(AcademicNarrativeGenerator::class)->generate($analysis);

        $this->assertStringStartsWith('Draf narasi akademik:', $narrative);
        $this->assertStringContainsString('respons yang memenuhi kriteria analisis deskriptif', $narrative);
        $this->assertStringContainsString('rerata', $narrative);
        $this->assertStringContainsString('diverifikasi oleh peneliti dan pembimbing', $narrative);
        $this->assertStringNotContainsString('signifikan', mb_strtolower($narrative));
        $this->assertStringNotContainsString('p-value', mb_strtolower($narrative));
        $this->assertStringNotContainsString('p value', mb_strtolower($narrative));
        $this->assertStringNotContainsString('kausal', mb_strtolower($narrative));
        $this->assertStringNotContainsString('efektivitas', mb_strtolower($narrative));
    }
}
