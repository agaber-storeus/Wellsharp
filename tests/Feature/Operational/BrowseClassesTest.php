<?php

namespace Tests\Feature\Operational;

use App\Models\ClassStaffAssignment;
use App\Models\TrainingClass;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseClassesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_proctor_browse_page_loads_all_real_classes_into_alpine(): void
    {
        $proctor = User::factory()->proctor()->create();
        $provider = TrainingProvider::factory()->create([
            'name' => 'Browse Training Center',
            'address' => 'Cairo, Egypt',
        ]);
        $assigned = TrainingClass::factory()->create([
            'class_number' => 'BROWSE-REAL-001',
            'training_provider_id' => $provider->id,
        ]);
        $otherAssigned = TrainingClass::factory()->create(['class_number' => 'BROWSE-REAL-002']);
        $unassigned = TrainingClass::factory()->create(['class_number' => 'BROWSE-HIDDEN-001']);

        ClassStaffAssignment::factory()->create([
            'class_id' => $assigned->id,
            'user_id' => $proctor->id,
            'assignment_role' => 'proctor',
        ]);
        ClassStaffAssignment::factory()->create([
            'class_id' => $otherAssigned->id,
            'user_id' => $proctor->id,
            'assignment_role' => 'proctor',
        ]);

        $response = $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.browse', ['search' => 'BROWSE-REAL-001']));

        $response->assertOk()
            ->assertSee('Search Options')
            ->assertSee('wellsharpBrowseClasses', false)
            ->assertSee('BROWSE-REAL-001')
            ->assertSee('BROWSE-REAL-002')
            ->assertSee('Browse Training Center')
            ->assertSee('Cairo, Egypt')
            ->assertSee('BROWSE-HIDDEN-001')
            ->assertSee('data-class-modal="details"', false)
            ->assertSee('browse-sort-button', false)
            ->assertSee('browse-table-scroll', false)
            ->assertDontSee('browse-column-controls', false)
            ->assertDontSee('browse-columns-button', false)
            ->assertSee('browse-column-resizer', false)
            ->assertSee('data-resize-column-key', false)
            ->assertSee('mousedown', false)
            ->assertSee('startColumnResize', false)
            ->assertSee('mousemove', false)
            ->assertSee('__wellsharpBrowseColumnResizeBound', false)
            ->assertSee('resizeColumn', false)
            ->assertSee('browse-status-value', false)
            ->assertSee('visibleColumns', false)
            ->assertSee('Class Title or ID:');
    }

    public function test_instructor_browse_results_uses_the_same_database_backed_alpine_table(): void
    {
        $instructor = User::factory()->instructor()->create();
        $trainingClass = TrainingClass::factory()->create(['class_number' => 'INSTRUCTOR-BROWSE-001']);
        ClassStaffAssignment::factory()->create([
            'class_id' => $trainingClass->id,
            'user_id' => $instructor->id,
            'assignment_role' => 'instructor',
        ]);

        $this->actingAs($instructor)
            ->withSession(['auth.session_version' => $instructor->session_version])
            ->get(route('instructor.browse.results', ['search' => 'INSTRUCTOR-BROWSE-001']))
            ->assertOk()
            ->assertSee('wellsharpBrowseClasses', false)
            ->assertSee('INSTRUCTOR-BROWSE-001')
            ->assertSee('Search Options');
    }
}
