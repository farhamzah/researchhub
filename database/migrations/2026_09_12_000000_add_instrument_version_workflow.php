<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->string('instrument_code')->nullable()->after('instrument_type');
            $table->string('instrument_version')->nullable()->after('instrument_code');
            $table->string('workflow_state')->nullable()->after('instrument_version')->index();
            $table->foreignUuid('supersedes_survey_id')->nullable()->after('parent_survey_id')->constrained('surveys')->nullOnDelete();
            $table->unique(['project_id', 'instrument_code', 'instrument_version'], 'surveys_project_instrument_version_unique');
        });

        Schema::table('survey_supervisor_review_rounds', function (Blueprint $table): void {
            $table->string('instrument_version')->nullable()->after('purpose');
            $table->string('workflow_state_at_open')->nullable()->after('instrument_version');
            $table->timestamp('finalized_at')->nullable()->after('snapshot_taken_at');
        });

        Schema::create('survey_workflow_transitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable();
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->string('instrument_version');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['survey_id', 'created_at']);
            $table->index(['survey_id', 'to_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_workflow_transitions');
        Schema::table('survey_supervisor_review_rounds', function (Blueprint $table): void {
            $table->dropColumn(['instrument_version', 'workflow_state_at_open', 'finalized_at']);
        });
        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropUnique('surveys_project_instrument_version_unique');
            $table->dropConstrainedForeignId('supersedes_survey_id');
            $table->dropIndex(['workflow_state']);
            $table->dropColumn(['instrument_code', 'instrument_version', 'workflow_state']);
        });
    }
};
