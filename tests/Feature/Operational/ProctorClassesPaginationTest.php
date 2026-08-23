<?php

namespace Tests\Feature\Operational;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProctorClassesPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_ongoing_and_upcoming_classes_are_paginated_client_side_from_real_database_rows(): void
    {
        $proctor = User::factory()->proctor()->create();
        $provider = TrainingProvider::factory()->create(['name' => 'Pagination Training Center', 'address' => 'Cairo, Egypt']);

        // 6 active ("ongoing") + 6 planned ("upcoming") = 12 rows, well above the 4-per-page window.
        for ($i = 1; $i <= 6; $i++) {
            TrainingClass::factory()->create([
                'class_number' => 'ONGOING-'.$i,
                'course_id' => Course::factory()->create(['name' => 'Ongoing Course '.$i])->id,
                'training_provider_id' => $provider->id,
                'proctor_id' => $proctor->id,
                'status' => 'active',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(2),
            ]);
        }

        for ($i = 1; $i <= 6; $i++) {
            TrainingClass::factory()->create([
                'class_number' => 'UPCOMING-'.$i,
                'course_id' => Course::factory()->create(['name' => 'Upcoming Course '.$i])->id,
                'training_provider_id' => $provider->id,
                'proctor_id' => $proctor->id,
                'status' => 'planned',
                'starts_at' => now()->addDays(5),
                'ends_at' => now()->addDays(7),
            ]);
        }

        $response = $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'));

        $response->assertOk()
            ->assertSee('wellsharpClassesPage', false)
            // Every row must be present in the server-rendered Alpine data payload even though
            // only 4 render per page client-side — the pagination is client-side, not a server query cutoff.
            ->assertSee('Ongoing Course 1')
            ->assertSee('Ongoing Course 6')
            ->assertSee('Upcoming Course 1')
            ->assertSee('Upcoming Course 6')
            ->assertSee('Pagination Training Center')
            // Pagination + Alpine wiring for the Ongoing/Upcoming table.
            ->assertSee('x-for="row in pageRows"', false)
            ->assertSee('perPage: 4', false)
            ->assertSee('class="pagination"', false)
            ->assertSee('results-caption', false)
            // Calendar Schedule renders each multi-day class as a single centered bar spanning its grid columns.
            ->assertSee('calendar-reference-grid', false)
            ->assertSee('calendar-event-span', false)
            ->assertSee("'calendar-event-' + bar.status", false);
    }

    public function test_calendar_and_table_rows_are_sourced_from_the_classes_table_via_training_class_model(): void
    {
        $proctor = User::factory()->proctor()->create();
        $provider = TrainingProvider::factory()->create(['name' => 'Calendar Source Center', 'address' => 'Kuwait City, Kuwait']);
        $course = Course::factory()->create(['name' => 'Calendar Source Course']);

        $trainingClass = TrainingClass::factory()->create([
            'class_number' => 'CAL-SOURCE-001',
            'course_id' => $course->id,
            'training_provider_id' => $provider->id,
            'proctor_id' => $proctor->id,
            'status' => 'active',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(4),
        ]);

        $this->assertDatabaseHas('classes', [
            'id' => $trainingClass->id,
            'course_id' => $course->id,
            'training_provider_id' => $provider->id,
            'status' => 'active',
        ]);

        $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk()
            ->assertSee('Calendar Source Course')
            ->assertSee('Calendar Source Center')
            ->assertSee($trainingClass->public_id);
    }

    public function test_class_title_shows_the_linked_exams_name_not_the_course_name(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create(['name' => 'Assessment Preparation', 'code' => 'AP']);
        $trainingClass = TrainingClass::factory()->create([
            'class_number' => 'TITLE-SOURCE-001',
            'course_id' => $course->id,
            'proctor_id' => $proctor->id,
            'status' => 'planned',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $exam = Exam::create(['course_id' => $course->id, 'name' => 'Well Control Refresher Exam', 'question_order_mode' => 'static', 'status' => 'published']);
        $group = Group::create(['name' => 'Title Source Group', 'status' => 'active']);
        ExamSchedule::create([
            'exam_id' => $exam->id, 'group_id' => $group->id, 'training_class_id' => $trainingClass->id,
            'start_date' => $trainingClass->starts_at->toDateString(), 'end_date' => $trainingClass->ends_at->toDateString(),
            'duration_minutes' => 60, 'status' => 'scheduled',
        ]);

        $this->assertSame('Well Control Refresher Exam', $trainingClass->fresh(['examSchedules.exam'])->displayTitle());

        $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk()
            ->assertSee('Well Control Refresher Exam');
    }

    public function test_class_title_falls_back_to_the_course_when_no_exam_is_linked(): void
    {
        $proctor = User::factory()->proctor()->create();
        $course = Course::factory()->create(['name' => 'Unlinked Course', 'code' => 'UNL']);
        $trainingClass = TrainingClass::factory()->create([
            'class_number' => 'TITLE-FALLBACK-001',
            'course_id' => $course->id,
            'proctor_id' => $proctor->id,
            'status' => 'planned',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $this->assertSame('UNL — Unlinked Course', $trainingClass->fresh(['examSchedules.exam'])->displayTitle());

        $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk()
            ->assertSee('Unlinked Course');
    }

    public function test_calendar_schedule_excludes_completed_and_cancelled_classes(): void
    {
        $proctor = User::factory()->proctor()->create();

        $active = TrainingClass::factory()->create([
            'class_number' => 'CAL-ACTIVE-001',
            'course_id' => Course::factory()->create(['name' => 'Active Calendar Course'])->id,
            'proctor_id' => $proctor->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $planned = TrainingClass::factory()->create([
            'class_number' => 'CAL-PLANNED-001',
            'course_id' => Course::factory()->create(['name' => 'Planned Calendar Course'])->id,
            'proctor_id' => $proctor->id,
            'status' => 'planned',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(7),
        ]);
        $completed = TrainingClass::factory()->create([
            'class_number' => 'CAL-COMPLETED-001',
            'course_id' => Course::factory()->create(['name' => 'Completed Calendar Course'])->id,
            'proctor_id' => $proctor->id,
            'status' => 'completed',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(8),
        ]);
        $cancelled = TrainingClass::factory()->create([
            'class_number' => 'CAL-CANCELLED-001',
            'course_id' => Course::factory()->create(['name' => 'Cancelled Calendar Course'])->id,
            'proctor_id' => $proctor->id,
            'status' => 'cancelled',
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(18),
        ]);

        $response = $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.classes'))
            ->assertOk();

        $calendarEvents = $this->extractCalendarEvents($response->getContent());
        $eventIds = collect($calendarEvents)->pluck('id')->all();

        $this->assertContains($active->public_id, $eventIds);
        $this->assertContains($planned->public_id, $eventIds);
        $this->assertNotContains($completed->public_id, $eventIds);
        $this->assertNotContains($cancelled->public_id, $eventIds);
    }

    private function extractCalendarEvents(string $html): array
    {
        preg_match('/wellsharpClassesPage\(JSON\.parse\(\'(.*?)\'\)/s', $html, $matches);
        $this->assertNotEmpty($matches, 'Could not locate the wellsharpClassesPage(...) Alpine payload in the response.');

        $innerJson = json_decode('"'.$matches[1].'"');

        return json_decode($innerJson, true);
    }
}
