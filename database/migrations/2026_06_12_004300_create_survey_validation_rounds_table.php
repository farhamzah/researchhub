<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_validation_rounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('research_project_id')->nullable()->constrained('research_projects')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('method')->default('expert_judgment');
            $table->unsignedTinyInteger('rating_scale_min')->default(1);
            $table->unsignedTinyInteger('rating_scale_max')->default(4);
            $table->string('status')->default('draft')->index();
            $table->text('instructions')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['survey_id', 'status']);
            $table->index(['research_project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_validation_rounds');
    }
};
