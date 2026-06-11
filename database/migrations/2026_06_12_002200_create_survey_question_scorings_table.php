<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_question_scorings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('survey_question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->foreignUuid('survey_indicator_id')->nullable()->constrained('survey_indicators')->nullOnDelete();
            $table->boolean('is_scored')->default(true);
            $table->decimal('score_min', 10, 4)->nullable();
            $table->decimal('score_max', 10, 4)->nullable();
            $table->decimal('weight', 10, 4)->default(1);
            $table->boolean('is_reverse_scored')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique('survey_question_id');
            $table->index(['survey_id', 'survey_indicator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_question_scorings');
    }
};
