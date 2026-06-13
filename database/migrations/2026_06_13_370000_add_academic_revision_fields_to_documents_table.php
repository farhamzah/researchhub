<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('documents', 'document_type')) {
                $table->string('document_type')->nullable()->after('visibility');
            }

            if (! Schema::hasColumn('documents', 'version_label')) {
                $table->string('version_label')->nullable()->after('document_type');
            }

            if (! Schema::hasColumn('documents', 'version_number')) {
                $table->unsignedInteger('version_number')->default(1)->after('version_label');
            }

            if (! Schema::hasColumn('documents', 'is_current')) {
                $table->boolean('is_current')->default(true)->after('version_number');
            }

            if (! Schema::hasColumn('documents', 'reviewer_name')) {
                $table->string('reviewer_name')->nullable()->after('is_current');
            }

            if (! Schema::hasColumn('documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewer_name');
            }

            if (! Schema::hasColumn('documents', 'revision_due_date')) {
                $table->date('revision_due_date')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('documents', 'next_action')) {
                $table->string('next_action')->nullable()->after('revision_due_date');
            }

            if (! Schema::hasColumn('documents', 'revision_summary')) {
                $table->text('revision_summary')->nullable()->after('next_action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            foreach ([
                'revision_summary',
                'next_action',
                'revision_due_date',
                'reviewed_at',
                'reviewer_name',
                'is_current',
                'version_number',
                'version_label',
                'document_type',
            ] as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
