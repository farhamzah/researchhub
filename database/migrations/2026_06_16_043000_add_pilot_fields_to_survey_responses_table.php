<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table): void {
            $table->boolean('is_test_response')->default(false)->after('score_total');
            $table->string('test_label')->nullable()->after('is_test_response');
            $table->uuid('pilot_run_id')->nullable()->after('test_label');
            $table->boolean('excluded_from_analysis')->default(false)->after('pilot_run_id');

            $table->index(['survey_id', 'is_test_response']);
            $table->index(['survey_id', 'excluded_from_analysis']);
            $table->index('pilot_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table): void {
            $table->dropIndex(['survey_id', 'is_test_response']);
            $table->dropIndex(['survey_id', 'excluded_from_analysis']);
            $table->dropIndex(['pilot_run_id']);
            $table->dropColumn([
                'is_test_response',
                'test_label',
                'pilot_run_id',
                'excluded_from_analysis',
            ]);
        });
    }
};
