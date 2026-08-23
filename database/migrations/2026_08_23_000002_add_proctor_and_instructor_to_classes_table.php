<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both columns are nullable at the DB level on purpose: existing Classes have
     * no deterministic Proctor/Instructor to backfill (see BUSINESS_RULES.md), so
     * "every Class must have exactly one Proctor and one Instructor" is enforced
     * at the application/validation layer for new and edited Classes instead of
     * as a NOT NULL constraint here. This keeps legacy rows valid without
     * fabricating assignments.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->foreignId('proctor_id')->nullable()->after('training_provider_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('instructor_id')->nullable()->after('proctor_id')->constrained('users')->restrictOnDelete();
            $table->index(['proctor_id', 'status']);
            $table->index(['instructor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropForeign(['proctor_id']);
            $table->dropForeign(['instructor_id']);
            $table->dropIndex(['proctor_id', 'status']);
            $table->dropIndex(['instructor_id', 'status']);
            $table->dropColumn(['proctor_id', 'instructor_id']);
        });
    }
};
