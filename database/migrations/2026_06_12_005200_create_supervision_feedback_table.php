<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supervision_review_link_id')->constrained('supervision_review_links')->cascadeOnDelete();
            $table->foreignUuid('supervision_session_id')->constrained('supervision_sessions')->cascadeOnDelete();
            $table->string('decision');
            $table->text('general_feedback')->nullable();
            $table->text('revision_notes')->nullable();
            $table->text('recommended_next_steps')->nullable();
            $table->text('supervisor_note')->nullable();
            $table->timestamps();

            $table->unique('supervision_review_link_id');
            $table->index(['supervision_session_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_feedback');
    }
};
