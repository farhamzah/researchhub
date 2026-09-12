<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_supervisor_reviewer_hubs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->constrained('research_projects')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supervisor_name');
            $table->string('supervisor_email')->nullable();
            $table->string('supervisor_code');
            $table->string('token_hash')->nullable()->unique();
            $table->timestamp('token_created_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['research_project_id', 'supervisor_code'], 'supervisor_reviewer_hubs_project_code_unique');
        });

        Schema::table('survey_supervisor_reviewers', function (Blueprint $table): void {
            $table->foreignUuid('reviewer_hub_id')
                ->nullable()
                ->after('survey_supervisor_review_round_id')
                ->constrained('survey_supervisor_reviewer_hubs')
                ->nullOnDelete();
            $table->index(['reviewer_hub_id', 'status'], 'supervisor_reviewers_hub_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('survey_supervisor_reviewers', function (Blueprint $table): void {
            $table->dropIndex('supervisor_reviewers_hub_status_index');
            $table->dropConstrainedForeignId('reviewer_hub_id');
        });

        Schema::dropIfExists('survey_supervisor_reviewer_hubs');
    }
};
