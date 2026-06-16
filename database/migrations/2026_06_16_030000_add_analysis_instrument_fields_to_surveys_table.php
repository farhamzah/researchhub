<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->string('instrument_type')->nullable()->after('identity_mode')->index();
            $table->foreignUuid('parent_survey_id')->nullable()->after('instrument_type')->constrained('surveys')->nullOnDelete();
            $table->string('analysis_group_key')->nullable()->after('parent_survey_id')->index();

            $table->index(['project_id', 'instrument_type'], 'surveys_project_instrument_type_idx');
            $table->index(['parent_survey_id', 'instrument_type'], 'surveys_parent_instrument_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropIndex('surveys_project_instrument_type_idx');
            $table->dropIndex('surveys_parent_instrument_type_idx');
            $table->dropForeign(['parent_survey_id']);
            $table->dropColumn(['instrument_type', 'parent_survey_id', 'analysis_group_key']);
        });
    }
};
