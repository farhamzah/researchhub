<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_validation_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_validation_assignment_id')->constrained('survey_validation_assignments')->cascadeOnDelete();
            $table->foreignUuid('survey_question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('relevance_score')->nullable();
            $table->unsignedTinyInteger('clarity_score')->nullable();
            $table->unsignedTinyInteger('language_score')->nullable();
            $table->unsignedTinyInteger('appropriateness_score')->nullable();
            $table->text('comment')->nullable();
            $table->string('recommendation')->nullable();
            $table->timestamps();

            $table->unique(['survey_validation_assignment_id', 'survey_question_id'], 'survey_validation_score_unique_question');
            $table->index(['survey_question_id', 'recommendation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_validation_scores');
    }
};
