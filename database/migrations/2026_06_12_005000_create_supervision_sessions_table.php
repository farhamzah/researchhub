<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->constrained('research_projects')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('meeting_type')->default('regular_guidance')->index();
            $table->string('status')->default('draft')->index();
            $table->text('agenda')->nullable();
            $table->text('progress_report')->nullable();
            $table->text('questions')->nullable();
            $table->text('requested_feedback')->nullable();
            $table->text('next_plan')->nullable();
            $table->text('notes')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['research_project_id', 'status']);
            $table->index(['created_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_sessions');
    }
};
