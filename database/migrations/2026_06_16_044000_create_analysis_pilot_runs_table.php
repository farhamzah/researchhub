<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_pilot_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignUuid('target_survey_id')->nullable()->constrained('surveys')->nullOnDelete();
            $table->string('audience_type')->index();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('status')->default('draft')->index();
            $table->json('checklist_json')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('passed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'audience_type', 'status']);
            $table->index(['target_survey_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_pilot_runs');
    }
};
