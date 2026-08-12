<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $columns = array_values(array_filter(['solution_video_url', 'attachment_enabled'], fn (string $column): bool => Schema::hasColumn('questions', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('questions', 'solution_video_url')) {
                $table->string('solution_video_url', 2048)->nullable()->after('solution_text');
            }
            if (! Schema::hasColumn('questions', 'attachment_enabled')) {
                $table->boolean('attachment_enabled')->default(false)->after('solution_video_url');
            }
        });
    }
};
