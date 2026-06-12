<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_validation_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_validation_round_id')->constrained('survey_validation_rounds')->cascadeOnDelete();
            $table->foreignUuid('expert_validator_id')->constrained('expert_validators')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('token_hash')->nullable()->unique();
            $table->timestamp('token_created_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['survey_validation_round_id', 'expert_validator_id'], 'survey_validation_assignment_unique_validator');
            $table->index(['survey_validation_round_id', 'status']);
            $table->index(['expert_validator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_validation_assignments');
    }
};
