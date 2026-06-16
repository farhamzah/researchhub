<?php

use App\Models\SurveyDistributionRecipient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_distribution_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('survey_distribution_batches')->cascadeOnDelete();
            $table->foreignUuid('target_survey_id')->nullable()->constrained('surveys')->nullOnDelete();
            $table->foreignUuid('validation_assignment_id')->nullable()->constrained('survey_validation_assignments')->nullOnDelete();
            $table->foreignUuid('readability_participant_id')->nullable()->constrained('survey_readability_participants')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('institution')->nullable();
            $table->string('role_label')->nullable();
            $table->text('link_url')->nullable();
            $table->string('status')->default(SurveyDistributionRecipient::STATUS_DRAFT);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index('target_survey_id');
            $table->index('validation_assignment_id');
            $table->index('readability_participant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_distribution_recipients');
    }
};
