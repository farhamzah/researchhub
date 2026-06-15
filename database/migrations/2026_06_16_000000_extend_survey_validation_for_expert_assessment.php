<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_validation_rounds', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating_scale_max')->default(5)->change();
        });

        Schema::table('survey_validation_scores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('content_relevance_score')->nullable()->after('survey_question_id');
            $table->unsignedTinyInteger('language_clarity_score')->nullable()->after('content_relevance_score');
            $table->unsignedTinyInteger('construct_alignment_score')->nullable()->after('language_clarity_score');
            $table->unsignedTinyInteger('measurability_score')->nullable()->after('construct_alignment_score');
            $table->unsignedTinyInteger('feasibility_score')->nullable()->after('measurability_score');
            $table->unsignedTinyInteger('ethical_suitability_score')->nullable()->after('feasibility_score');
        });

        Schema::create('survey_validation_recommendations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_validation_assignment_id')
                ->constrained('survey_validation_assignments')
                ->cascadeOnDelete();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('feasibility_decision');
            $table->text('general_comments')->nullable();
            $table->text('revision_suggestions')->nullable();
            $table->timestamps();

            $table->unique('survey_validation_assignment_id', 'survey_validation_recommendation_unique_assignment');
            $table->index(['survey_id', 'feasibility_decision']);
        });

        Schema::create('survey_validation_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('source_assignment_id')
                ->nullable()
                ->constrained('survey_validation_assignments')
                ->nullOnDelete();
            $table->text('validator_comment');
            $table->text('revision_action')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('researcher_note')->nullable();
            $table->timestamps();

            $table->index(['survey_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_validation_revisions');
        Schema::dropIfExists('survey_validation_recommendations');

        Schema::table('survey_validation_scores', function (Blueprint $table): void {
            $table->dropColumn([
                'content_relevance_score',
                'language_clarity_score',
                'construct_alignment_score',
                'measurability_score',
                'feasibility_score',
                'ethical_suitability_score',
            ]);
        });

        Schema::table('survey_validation_rounds', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating_scale_max')->default(4)->change();
        });
    }
};
