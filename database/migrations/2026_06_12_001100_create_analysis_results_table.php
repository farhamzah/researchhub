<?php

use App\Models\AnalysisJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('analysis_job_id')->constrained('analysis_jobs')->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('research_projects')->cascadeOnDelete();
            $table->foreignUuid('survey_id')->nullable()->constrained('surveys')->cascadeOnDelete();
            $table->string('type')->default(AnalysisJob::TYPE_SURVEY_DESCRIPTIVE);
            $table->string('title');
            $table->json('summary')->nullable();
            $table->json('result_payload');
            $table->timestamps();

            $table->index(['project_id', 'type']);
            $table->index(['survey_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
