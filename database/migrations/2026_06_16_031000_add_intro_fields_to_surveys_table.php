<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->string('intro_title')->nullable()->after('description');
            $table->text('intro_text')->nullable()->after('intro_title');
            $table->string('estimated_duration')->nullable()->after('intro_text');
            $table->text('privacy_statement')->nullable()->after('estimated_duration');
            $table->text('respondent_instruction')->nullable()->after('privacy_statement');
            $table->text('consent_text')->nullable()->after('respondent_instruction');
            $table->boolean('require_consent_before_start')->default(false)->after('consent_text');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropColumn([
                'intro_title',
                'intro_text',
                'estimated_duration',
                'privacy_statement',
                'respondent_instruction',
                'consent_text',
                'require_consent_before_start',
            ]);
        });
    }
};
