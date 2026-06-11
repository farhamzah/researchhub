<?php

use App\Models\SurveyResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('respondent_id')->nullable()->constrained('respondents')->nullOnDelete();
            $table->string('response_token_hash', 64)->nullable()->unique();
            $table->string('status')->default(SurveyResponse::STATUS_STARTED);
            $table->timestamp('submitted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->decimal('score_total', 12, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'status']);
            $table->index(['respondent_id', 'status']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
