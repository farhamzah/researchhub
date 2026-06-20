<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_supervisor_review_rounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('research_project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->string('status')->default('draft')->index();
            $table->date('due_date')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('snapshot_taken_at')->nullable();
            $table->string('snapshot_hash')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['survey_id', 'status']);
            $table->index(['research_project_id', 'status']);
        });

        Schema::create('survey_supervisor_reviewers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_supervisor_review_round_id')
                ->constrained('survey_supervisor_review_rounds')
                ->cascadeOnDelete();
            $table->string('supervisor_name');
            $table->string('supervisor_email')->nullable();
            $table->string('supervisor_code')->nullable();
            $table->string('role')->nullable();
            $table->string('status')->default('not_opened')->index();
            $table->string('token_hash')->nullable()->unique();
            $table->timestamp('token_created_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('final_decision')->nullable();
            $table->text('final_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['survey_supervisor_review_round_id', 'status'], 'supervisor_reviewers_round_status_index');
        });

        Schema::create('survey_supervisor_review_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_supervisor_reviewer_id')
                ->constrained('survey_supervisor_reviewers')
                ->cascadeOnDelete();
            $table->foreignUuid('survey_supervisor_review_round_id')
                ->constrained('survey_supervisor_review_rounds')
                ->cascadeOnDelete();
            $table->foreignUuid('survey_question_id')->nullable()->constrained('survey_questions')->nullOnDelete();
            $table->string('comment_type')->index();
            $table->string('target_key')->nullable();
            $table->string('target_label')->nullable();
            $table->text('comment');
            $table->text('suggested_revision')->nullable();
            $table->string('severity')->nullable();
            $table->string('decision')->nullable();
            $table->timestamps();

            $table->index(['survey_supervisor_review_round_id', 'comment_type'], 'supervisor_comments_round_type_index');
        });

        Schema::create('survey_supervisor_review_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('survey_supervisor_review_round_id')
                ->constrained('survey_supervisor_review_rounds')
                ->cascadeOnDelete();
            $table->foreignUuid('survey_supervisor_reviewer_id')
                ->nullable()
                ->constrained('survey_supervisor_reviewers')
                ->nullOnDelete();
            $table->foreignUuid('survey_supervisor_review_comment_id')
                ->nullable()
                ->constrained('survey_supervisor_review_comments')
                ->nullOnDelete();
            $table->string('item_label');
            $table->string('supervisor_code')->nullable();
            $table->text('comment');
            $table->text('suggested_revision')->nullable();
            $table->string('severity')->nullable();
            $table->text('researcher_response')->nullable();
            $table->text('action_taken')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('revised_version')->nullable();
            $table->timestamp('revised_at')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'status']);
            $table->index(['survey_supervisor_review_round_id', 'status'], 'supervisor_revisions_round_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_supervisor_review_revisions');
        Schema::dropIfExists('survey_supervisor_review_comments');
        Schema::dropIfExists('survey_supervisor_reviewers');
        Schema::dropIfExists('survey_supervisor_review_rounds');
    }
};
