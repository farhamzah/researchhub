<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('page_id')->nullable()->constrained('survey_pages')->nullOnDelete();
            $table->string('question_key');
            $table->string('type');
            $table->text('label');
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['survey_id', 'question_key']);
            $table->index(['survey_id', 'page_id', 'sort_order']);
            $table->index(['survey_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
