<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'proctor_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('proctor_id', 32)->nullable()->unique()->after('wellsharp_id');
            });
        }

        $proctorRoleId = DB::table('roles')->where('key', 'proctor')->value('id');
        if ($proctorRoleId) {
            DB::table('users')->where('current_role_id', $proctorRoleId)->whereNull('proctor_id')->orderBy('id')->get(['id'])->each(function (object $user): void {
                do {
                    $proctorId = 'PR-'.Str::upper(Str::random(10));
                } while (DB::table('users')->where('proctor_id', $proctorId)->exists());

                DB::table('users')->where('id', $user->id)->update(['proctor_id' => $proctorId]);
            });
        }

        Schema::table('exam_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('exam_schedules', 'training_class_id')) {
                $table->foreignId('training_class_id')->nullable()->after('group_id')->constrained('classes')->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_schedules', 'override_started_at')) {
                $table->timestampTz('override_started_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('exam_schedules', 'override_ended_at')) {
                $table->timestampTz('override_ended_at')->nullable()->after('override_started_at');
            }
            if (! Schema::hasColumn('exam_schedules', 'override_started_by_user_id')) {
                $table->foreignId('override_started_by_user_id')->nullable()->after('override_ended_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('exam_schedules', 'override_ended_by_user_id')) {
                $table->foreignId('override_ended_by_user_id')->nullable()->after('override_started_by_user_id')->constrained('users')->nullOnDelete();
            }
            $table->index(['training_class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->dropForeign(['override_ended_by_user_id']);
            $table->dropForeign(['override_started_by_user_id']);
            $table->dropForeign(['training_class_id']);
            $table->dropIndex(['training_class_id', 'status']);
            $table->dropColumn(['training_class_id', 'override_started_at', 'override_ended_at', 'override_started_by_user_id', 'override_ended_by_user_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['proctor_id']);
            $table->dropColumn('proctor_id');
        });
    }
};
