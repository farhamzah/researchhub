<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_synthesis_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->string('source_type')->index();
            $table->string('source_label')->nullable();
            $table->string('theme')->index();
            $table->text('finding');
            $table->text('evidence_summary')->nullable();
            $table->string('evidence_metric')->nullable();
            $table->string('priority_level')->default('medium')->index();
            $table->text('design_implication')->nullable();
            $table->text('development_decision')->nullable();
            $table->string('mapped_module')->nullable()->index();
            $table->string('status')->default('proposed')->index();
            $table->text('researcher_note')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'source_type', 'theme'], 'analysis_synthesis_survey_source_theme_idx');
            $table->index(['survey_id', 'priority_level', 'status'], 'analysis_synthesis_survey_priority_status_idx');
            $table->unique(['survey_id', 'source_type', 'theme', 'finding'], 'analysis_synthesis_unique_finding');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_synthesis_items');
    }
};
