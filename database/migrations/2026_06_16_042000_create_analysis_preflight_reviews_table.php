<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_preflight_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('total_checks')->default(0);
            $table->unsignedInteger('passed_checks')->default(0);
            $table->unsignedInteger('warning_checks')->default(0);
            $table->unsignedInteger('failed_checks')->default(0);
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('ready_marked_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_preflight_reviews');
    }
};
