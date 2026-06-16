<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_readability_rounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('open')->index();
            $table->unsignedInteger('target_participants')->nullable()->default(10);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'status']);
        });

        Schema::create('survey_readability_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_readability_round_id')
                ->constrained('survey_readability_rounds')
                ->cascadeOnDelete();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('participant_name')->nullable();
            $table->string('participant_email')->nullable();
            $table->string('participant_type')->nullable();
            $table->string('institution')->nullable();
            $table->string('token_hash')->nullable()->unique();
            $table->timestamp('token_created_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['survey_readability_round_id', 'status'], 'survey_readability_participants_round_status_idx');
            $table->index(['survey_id', 'status']);
        });

        Schema::create('survey_readability_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_readability_participant_id')
                ->constrained('survey_readability_participants')
                ->cascadeOnDelete();
            $table->foreignUuid('survey_readability_round_id')
                ->constrained('survey_readability_rounds')
                ->cascadeOnDelete();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_clarity_score')->nullable();
            $table->unsignedTinyInteger('overall_length_score')->nullable();
            $table->unsignedTinyInteger('terminology_clarity_score')->nullable();
            $table->unsignedTinyInteger('answer_option_clarity_score')->nullable();
            $table->unsignedTinyInteger('instruction_clarity_score')->nullable();
            $table->unsignedInteger('estimated_completion_minutes')->nullable();
            $table->boolean('has_confusing_items')->default(false);
            $table->text('confusing_items')->nullable();
            $table->text('general_comments')->nullable();
            $table->text('revision_suggestions')->nullable();
            $table->string('final_decision')->nullable();
            $table->timestamps();

            $table->unique('survey_readability_participant_id', 'survey_readability_response_unique_participant');
            $table->index(['survey_readability_round_id', 'final_decision'], 'survey_readability_responses_round_decision_idx');
        });

        Schema::create('survey_readability_question_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_readability_response_id')
                ->constrained('survey_readability_responses')
                ->cascadeOnDelete();
            $table->foreignUuid('survey_question_id')->nullable()->constrained('survey_questions')->nullOnDelete();
            $table->foreignUuid('survey_page_id')->nullable()->constrained('survey_pages')->nullOnDelete();
            $table->string('issue_type')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['survey_question_id', 'issue_type'], 'survey_readability_feedback_question_issue_idx');
        });

        Schema::create('survey_readability_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('survey_question_id')->nullable()->constrained('survey_questions')->nullOnDelete();
            $table->foreignUuid('source_response_id')
                ->nullable()
                ->constrained('survey_readability_responses')
                ->nullOnDelete();
            $table->text('issue_summary');
            $table->text('revision_action')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('researcher_note')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_readability_revisions');
        Schema::dropIfExists('survey_readability_question_feedback');
        Schema::dropIfExists('survey_readability_responses');
        Schema::dropIfExists('survey_readability_participants');
        Schema::dropIfExists('survey_readability_rounds');
    }
};
