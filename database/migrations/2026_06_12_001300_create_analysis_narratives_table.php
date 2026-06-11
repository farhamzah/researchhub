<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_narratives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('analysis_result_id')->constrained('analysis_results')->cascadeOnDelete();
            $table->string('section');
            $table->string('language')->default('id');
            $table->text('narrative');
            $table->timestamps();

            $table->index(['analysis_result_id', 'section', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_narratives');
    }
};
