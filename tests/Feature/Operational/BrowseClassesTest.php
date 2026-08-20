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
            ->assertSee('Class Title or ID:')
            ->assertSee('hasSearched', false)
            ->assertSee('trainingClass.id.slice(0, 8)', false)
            ->assertSee('locationOptions.countries', false)
            ->assertSee('onCountryChange', false)
            ->assertSee('onStateChange', false)
            ->assertSee('countriesnow.space', false)
            ->assertSee('id="browse-country"', false)
            ->assertSee('id="browse-state"', false)
            ->assertSee('id="browse-city"', false);
    }

    public function test_browse_page_wires_up_has_searched_from_the_query_string_filters(): void
    {
        $proctor = User::factory()->proctor()->create();
        $assigned = TrainingClass::factory()->create(['class_number' => 'BROWSE-HIDDEN-TABLE-001']);
        ClassStaffAssignment::factory()->create([
            'class_id' => $assigned->id,
            'user_id' => $proctor->id,
            'assignment_role' => 'proctor',
        ]);

        // hasSearched is computed client-side from the initial filters object handed to Alpine
        // (hasProvidedFilterValue), so what's verifiable server-side is that: (a) the results
        // block starts gated behind hasSearched, and (b) the filters JSON passed to Alpine
        // correctly reflects an empty vs populated query string.
        $emptyFiltersResponse = $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.browse'));

        $emptyFiltersResponse->assertOk()
            ->assertSee('x-show="hasSearched"', false)
            ->assertSee('hasSearched: hasProvidedFilterValue', false);

        $deepLinkResponse = $this->actingAs($proctor)
            ->withSession(['auth.session_version' => $proctor->session_version])
            ->get(route('proctor.browse', ['search' => 'BROWSE-DEEPLINK-001']));

        // @js() HEX-escapes quotes so the literal text in the markup uses \\u0022 in
        // place of ", not raw quote characters.
        $quote = chr(92).'u0022';
        $deepLinkResponse->assertOk()->assertSee($quote.'search'.$quote.':'.$quote.'BROWSE-DEEPLINK-001'.$quote, false);
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
