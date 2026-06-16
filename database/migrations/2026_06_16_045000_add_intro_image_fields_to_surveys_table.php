<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->string('intro_image_path')->nullable()->after('require_consent_before_start');
            $table->string('intro_image_alt_text')->nullable()->after('intro_image_path');
            $table->string('intro_image_caption', 500)->nullable()->after('intro_image_alt_text');
            $table->string('intro_image_source_note')->nullable()->after('intro_image_caption');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table): void {
            $table->dropColumn([
                'intro_image_path',
                'intro_image_alt_text',
                'intro_image_caption',
                'intro_image_source_note',
            ]);
        });
    }
};
