<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $columns = array_values(array_filter(['unit', 'lesson'], fn (string $column): bool => Schema::hasColumn('questions', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'unit')) {
                $table->string('unit', 160)->nullable()->after('difficulty');
            }
            if (! Schema::hasColumn('questions', 'lesson')) {
                $table->string('lesson', 160)->nullable()->after('unit');
            }
        });
    }
};
