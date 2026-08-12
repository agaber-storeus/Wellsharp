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
        Schema::create('exam_control_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('control_id', 32)->unique();
            $table->timestampsTz();
        });

        $eligibleRoleIds = DB::table('roles')->whereIn('key', ['proctor', 'instructor'])->pluck('id');
        $eligibleUsers = DB::table('users')->whereIn('current_role_id', $eligibleRoleIds)->orderBy('id')->get(['id', 'proctor_id']);
        $used = [];

        foreach ($eligibleUsers as $user) {
            $controlId = filled($user->proctor_id) && ! in_array($user->proctor_id, $used, true)
                ? $user->proctor_id
                : $this->generate($used);
            $used[] = $controlId;

            DB::table('exam_control_credentials')->insert([
                'user_id' => $user->id,
                'control_id' => $controlId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasColumn('users', 'proctor_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(['proctor_id']);
                $table->dropColumn('proctor_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'proctor_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('proctor_id', 32)->nullable()->unique()->after('wellsharp_id');
            });
        }

        DB::table('exam_control_credentials')->orderBy('id')->get(['user_id', 'control_id'])->each(function (object $credential): void {
            DB::table('users')->where('id', $credential->user_id)->update(['proctor_id' => $credential->control_id]);
        });

        Schema::dropIfExists('exam_control_credentials');
    }

    /** @param array<int, string> $used */
    private function generate(array $used): string
    {
        do {
            $controlId = 'PR-'.Str::upper(Str::random(10));
        } while (in_array($controlId, $used, true));

        return $controlId;
    }
};
