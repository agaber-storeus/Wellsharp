<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exam_schedules', 'class_id')) {
            Schema::table('exam_schedules', function (Blueprint $table): void {
                $table->string('class_id', 64)->nullable()->after('public_id');
            });
        }

        $duplicates = DB::table('exam_schedules')
            ->select('class_id', DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull('class_id')
            ->where('class_id', '<>', '')
            ->groupBy('class_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('duplicate_count', 'class_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot add unique exam_schedules.class_id index; duplicate Class IDs exist: '.$duplicates->keys()->join(', '));
        }

        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->unique('class_id', 'exam_schedules_class_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->dropUnique('exam_schedules_class_id_unique');
            $table->dropColumn('class_id');
        });
    }
};
