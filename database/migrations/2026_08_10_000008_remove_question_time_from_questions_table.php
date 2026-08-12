<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('questions', 'default_time_minutes')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->dropColumn('default_time_minutes');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('questions', 'default_time_minutes')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->unsignedInteger('default_time_minutes')->default(60)->after('default_marks');
            });
        }
    }
};
