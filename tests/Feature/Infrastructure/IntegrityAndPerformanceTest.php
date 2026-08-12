<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\TrainingClass;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntegrityAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create()->fresh();
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }

    public function test_required_queue_tables_are_migrated_and_foreign_keys_protect_history(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));

        $student = User::factory()->student()->create();
        $trainingClass = TrainingClass::factory()->create();
        Enrollment::create([
            'class_id' => $trainingClass->id,
            'student_user_id' => $student->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $trainingClass->delete();
    }

    public function test_database_unique_constraint_rejects_duplicate_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $trainingClass = TrainingClass::factory()->create();
        $attributes = [
            'class_id' => $trainingClass->id,
            'student_user_id' => $student->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ];
        Enrollment::create($attributes);

        $this->expectException(QueryException::class);
        Enrollment::create($attributes);
    }

    public function test_admin_course_index_query_count_does_not_grow_with_rows(): void
    {
        Course::factory()->create();
        $smallQueryCount = $this->countQueries(fn () => $this->get(route('admin.courses.index'))->assertOk());

        Course::factory()->count(20)->create();
        $largeQueryCount = $this->countQueries(fn () => $this->get(route('admin.courses.index'))->assertOk());

        $this->assertLessThanOrEqual($smallQueryCount + 5, $largeQueryCount);
    }

    private function countQueries(\Closure $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $callback();

        return $count;
    }
}
