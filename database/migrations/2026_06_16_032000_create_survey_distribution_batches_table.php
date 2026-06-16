<?php

use App\Models\SurveyDistributionBatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_distribution_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->string('audience_type');
            $table->string('title');
            $table->string('message_subject')->nullable();
            $table->text('message_body')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status')->default(SurveyDistributionBatch::STATUS_DRAFT);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'audience_type']);
            $table->index(['project_id', 'audience_type']);
            $table->index(['status', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_distribution_batches');
    }
};
