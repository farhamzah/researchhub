<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_document_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->string('title');
            $table->string('document_code')->nullable();
            $table->string('version')->default('Draft v1');
            $table->date('document_date')->nullable();
            $table->string('researcher_name')->nullable();
            $table->string('researcher_identifier')->nullable();
            $table->string('institution')->nullable();
            $table->string('study_program')->nullable();
            $table->string('promoter_name')->nullable();
            $table->text('co_promoter_names')->nullable();
            $table->string('stage')->default('ADDIE Analysis');
            $table->string('status')->default('draft')->index();
            $table->text('purpose_text')->nullable();
            $table->text('notes')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('survey_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_document_packages');
    }
};
