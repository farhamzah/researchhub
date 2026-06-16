<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_collection_targets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignUuid('target_survey_id')->nullable()->constrained('surveys')->nullOnDelete();
            $table->string('source_type')->index();
            $table->string('label');
            $table->unsignedInteger('minimum_count')->default(0);
            $table->unsignedInteger('target_count')->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['survey_id', 'source_type'], 'analysis_collection_targets_survey_source_unique');
            $table->index(['survey_id', 'target_survey_id'], 'analysis_collection_targets_surveys_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_collection_targets');
    }
};
