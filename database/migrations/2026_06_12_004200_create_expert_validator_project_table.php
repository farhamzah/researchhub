<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_validator_project', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_project_id')->constrained('research_projects')->cascadeOnDelete();
            $table->foreignUuid('expert_validator_id')->constrained('expert_validators')->cascadeOnDelete();
            $table->string('role');
            $table->string('expertise_scope')->nullable();
            $table->string('status')->default('invited');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['research_project_id', 'expert_validator_id', 'role'], 'expert_validator_project_unique_role');
            $table->index(['research_project_id', 'status']);
            $table->index(['expert_validator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_validator_project');
    }
};
