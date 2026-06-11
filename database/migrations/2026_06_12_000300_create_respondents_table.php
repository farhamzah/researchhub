<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respondents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('research_projects')->cascadeOnDelete();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('pseudonym_code')->nullable();
            $table->text('name')->nullable();
            $table->text('email')->nullable();
            $table->text('identifier')->nullable();
            $table->string('institution')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'pseudonym_code']);
            $table->index(['project_id', 'survey_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respondents');
    }
};
