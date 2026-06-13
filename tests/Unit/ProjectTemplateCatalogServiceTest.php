<?php

namespace Tests\Unit;

use App\Modules\Projects\Services\ProjectTemplateCatalogService;
use Tests\TestCase;

class ProjectTemplateCatalogServiceTest extends TestCase
{
    public function test_catalog_returns_required_project_templates(): void
    {
        $templates = app(ProjectTemplateCatalogService::class)->all();
        $keys = $templates->pluck('key');

        $this->assertTrue($keys->contains('dissertation_thesis'));
        $this->assertTrue($keys->contains('rd_addie'));
        $this->assertTrue($keys->contains('instrument_validation'));
        $this->assertTrue($keys->contains('journal_article'));
        $this->assertTrue($keys->contains('pharmvr_development_evaluation'));

        $pharmVr = app(ProjectTemplateCatalogService::class)->find('pharmvr_development_evaluation');

        $this->assertSame('PharmVR Development & Evaluation', $pharmVr['name']);
        $this->assertGreaterThanOrEqual(5, count($pharmVr['milestones']));
        $this->assertGreaterThanOrEqual(5, count($pharmVr['documents']));
        $this->assertIsArray($pharmVr['survey']);
    }
}
