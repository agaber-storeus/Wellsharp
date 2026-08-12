<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_memberships', function (Blueprint $table): void {
            // The old composite unique index was also being used by MySQL to
            // satisfy the group_id foreign key. Replace that dependency first.
            $table->index('group_id', 'group_memberships_group_id_index');
            $table->index('student_user_id', 'group_memberships_student_user_id_index');
        });

        Schema::table('group_memberships', function (Blueprint $table): void {
            // A student may leave and later rejoin the same group. Historical
            // removed rows must therefore not be unique by membership status.
            $table->dropUnique('group_memberships_group_id_student_user_id_status_unique');
        });
    }

    public function down(): void
    {
        Schema::table('group_memberships', function (Blueprint $table): void {
            $table->unique(['group_id', 'student_user_id', 'status']);
        });

        Schema::table('group_memberships', function (Blueprint $table): void {
            $table->dropIndex('group_memberships_group_id_index');
            $table->dropIndex('group_memberships_student_user_id_index');
        });
    }
};
