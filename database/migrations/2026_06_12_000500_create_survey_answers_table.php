<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_response_id')->constrained('survey_responses')->cascadeOnDelete();
            $table->foreignUuid('survey_question_id')->constrained('survey_questions')->restrictOnDelete();
            $table->string('question_key');
            $table->json('answer_value');
            $table->decimal('score', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['survey_response_id', 'survey_question_id']);
            $table->index(['survey_response_id', 'question_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
    }
};
