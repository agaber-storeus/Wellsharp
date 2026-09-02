<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Language;
use App\Models\Stack;
use App\Models\TrainingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderAndCourseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }

    public function test_admin_can_create_update_and_archive_provider(): void
    {
        $response = $this->post(route('admin.providers.store'), ['provider_number' => 'TP-001', 'name' => 'Provider One', 'email' => 'provider@example.test']);
        $response->assertRedirect();
        $provider = TrainingProvider::firstOrFail();
        $this->put(route('admin.providers.update', $provider), ['provider_number' => 'TP-001', 'name' => 'Updated Provider'])->assertRedirect();
        $this->patch(route('admin.providers.archive', $provider))->assertRedirect();
        $this->assertDatabaseHas('training_providers', ['id' => $provider->id, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_events', ['action' => 'training_provider.archived']);
    }

    public function test_admin_can_change_provider_status_from_the_provider_details_page(): void
    {
        $provider = TrainingProvider::factory()->create(['status' => 'active']);

        $this->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee('toggleStatus(provider)', false)
            ->assertSee('provider-status-toggle', false)
            ->assertSee('archiveProvider(provider)', false)
            ->assertSee('status_url', false);

        $this->get(route('admin.providers.show', $provider))
            ->assertOk()
            ->assertSee('Status')
            ->assertDontSee('providerStatusControl');

        $this->patchJson(route('admin.providers.status', $provider), ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('status', 'inactive')
            ->assertJsonPath('status_label', 'Inactive');

        $this->assertDatabaseHas('training_providers', ['id' => $provider->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('audit_events', ['action' => 'training_provider.status_updated', 'subject_id' => (string) $provider->id]);

        $this->patchJson(route('admin.providers.status', $provider), ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('status', 'active');

        $this->assertDatabaseHas('training_providers', ['id' => $provider->id, 'status' => 'active']);
        $this->patchJson(route('admin.providers.status', $provider), ['status' => 'archived'])
            ->assertUnprocessable();

        $this->patchJson(route('admin.providers.archive', $provider))
            ->assertOk()
            ->assertJsonPath('status', 'archived');
        $this->assertDatabaseHas('training_providers', ['id' => $provider->id, 'status' => 'archived']);
    }

    public function test_provider_form_uses_map_picker_and_stores_selected_location(): void
    {
        $this->get(route('admin.providers.create'))
            ->assertOk()
            ->assertSee('Provider location on map')
            ->assertSee('providerLocationMap')
            ->assertDontSee('Between -90 and 90');

        $this->post(route('admin.providers.store'), [
            'provider_number' => 'TP-MAP',
            'name' => 'Mapped Provider',
            'address' => 'Cairo, Egypt',
            'latitude' => '30.0444000',
            'longitude' => '31.2357000',
        ])->assertRedirect();

        $this->assertDatabaseHas('training_providers', ['provider_number' => 'TP-MAP', 'latitude' => 30.0444, 'longitude' => 31.2357]);
    }

    public function test_provider_details_page_shows_saved_location_on_map(): void
    {
        $provider = TrainingProvider::factory()->create([
            'name' => 'Mapped Provider',
            'address' => 'Cairo, Egypt',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ]);

        $this->get(route('admin.providers.show', $provider))
            ->assertOk()
            ->assertSee('Provider location')
            ->assertSee('providerLocationMap')
            ->assertSee('30.0444')
            ->assertSee('31.2357');
    }

    public function test_admin_course_create_form_renders_its_partial(): void
    {
        $this->get(route('admin.courses.create'))
            ->assertOk()
            ->assertSee('Subject code')
            ->assertDontSee('Training provider')
            ->assertDontSee("@include('admin.courses._form')");
    }

    public function test_provider_validation_search_pagination_and_archive_integrity(): void
    {
        $this->post(route('admin.providers.store'), ['provider_number' => 'TP-INVALID', 'name' => '', 'email' => 'invalid'])->assertSessionHasErrors(['name', 'email']);
        $this->post(route('admin.providers.store'), ['provider_number' => 'TP-DUP', 'name' => 'First Provider'])->assertRedirect();
        $this->post(route('admin.providers.store'), ['provider_number' => 'TP-DUP', 'name' => 'Second Provider'])->assertSessionHasErrors('provider_number');

        $provider = TrainingProvider::where('provider_number', 'TP-DUP')->firstOrFail();
        $course = Course::factory()->create();
        $this->patch(route('admin.providers.archive', $provider))->assertRedirect();
        $this->assertDatabaseHas('courses', ['id' => $course->id]);

        for ($i = 1; $i <= 16; $i++) {
            TrainingProvider::factory()->create(['name' => "Bulk Provider {$i}"]);
        }
        $this->get(route('admin.providers.index', ['search' => 'Bulk Provider', 'page' => 2]))->assertOk()->assertSee('Bulk Provider 16')->assertDontSee('Bulk Provider 1</td>');
    }

    public function test_course_reference_values_are_dynamic_and_duplicate_names_are_validated(): void
    {
        $this->post(route('admin.configuration.store', 'levels'), ['name' => 'Foundation'])->assertSessionHasErrors('name');
        $level = CourseLevel::where('slug', 'foundation')->firstOrFail();
        $this->patch(route('admin.configuration.toggle', ['levels', $level->id]))->assertRedirect();
        $this->assertFalse($level->fresh()->is_active);
        $student = User::factory()->student()->create()->fresh();
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])->get(route('admin.configuration.index'))->assertForbidden();
    }

    public function test_subject_configuration_uses_json_changes_and_drag_ordering(): void
    {
        $this->get(route('admin.configuration.index'))
            ->assertOk()
            ->assertSee('dragStart')
            ->assertSee('configuration-order-help')
            ->assertSee('grid-template-columns:minmax(0,1fr)')
            ->assertSee('x-on:blur="queueSave(section, row)"', false)
            ->assertSee('queueSave(section, row)', false)
            ->assertSee('x-on:click.prevent="toggle(section, row)"', false)
            ->assertSee('x-bind:aria-busy="row.toggling ? \'true\' : \'false\'"', false)
            ->assertDontSee('x-bind:disabled="row.toggling"', false)
            ->assertDontSee('x-on:click="save(section, row)"', false)
            ->assertDontSee('>Save</button>', false)
            ->assertDontSee('name="sort_order"');

        $created = $this->postJson(route('admin.configuration.store', 'supplements'), ['name' => 'AJAX Supplement'])
            ->assertCreated()
            ->assertJsonPath('row.name', 'AJAX Supplement')
            ->assertJsonPath('row.sort_order', 0);
        $supplementId = $created->json('row.id');

        $this->patchJson(route('admin.configuration.update', ['supplements', $supplementId]), ['name' => 'Updated Supplement'])
            ->assertOk()
            ->assertJsonPath('row.name', 'Updated Supplement');
        $this->assertDatabaseHas('supplements', [
            'id' => $supplementId,
            'name' => 'Updated Supplement',
        ]);
        $this->patchJson(route('admin.configuration.toggle', ['supplements', $supplementId]))
            ->assertOk()
            ->assertJsonPath('row.active', false);

        $first = Stack::create(['name' => 'Drag First', 'slug' => 'drag-first', 'sort_order' => 100, 'is_active' => true]);
        $second = Stack::create(['name' => 'Drag Second', 'slug' => 'drag-second', 'sort_order' => 101, 'is_active' => true]);
        $order = Stack::query()->orderByDesc('id')->pluck('id')->all();
        $this->patchJson(route('admin.configuration.reorder', 'stacks'), ['order' => $order])
            ->assertOk()
            ->assertJsonPath('message', 'Configuration order updated.');

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_admin_can_create_course_with_dynamic_reference_pivots(): void
    {
        $provider = TrainingProvider::factory()->create(['provider_number' => 'TP-002']);
        $level = CourseLevel::create(['name' => 'Operational', 'slug' => 'operational', 'sort_order' => 1, 'is_active' => true]);
        $stack = Stack::create(['name' => 'Stack One', 'slug' => 'stack-one', 'sort_order' => 1, 'is_active' => true]);
        $language = Language::create(['name' => 'Arabic', 'slug' => 'arabic', 'sort_order' => 1, 'is_active' => true]);

        $this->post(route('admin.courses.store'), [
            'code' => 'WS-101', 'name' => 'Foundations', 'course_level_id' => $level->id,
            'stack_ids' => [$stack->id], 'language_ids' => [$language->id], 'supplement_ids' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('courses', ['code' => 'WS-101']);
        $this->assertDatabaseHas('course_stack', ['stack_id' => $stack->id]);
        $this->assertDatabaseHas('course_language', ['language_id' => $language->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'course.created']);
    }

    public function test_course_update_syncs_pivots_and_audits_as_one_workflow(): void
    {
        $level = CourseLevel::create(['name' => 'Update Level', 'slug' => 'update-level', 'sort_order' => 1, 'is_active' => true]);
        $firstStack = Stack::create(['name' => 'First Stack', 'slug' => 'first-stack', 'sort_order' => 1, 'is_active' => true]);
        $secondStack = Stack::create(['name' => 'Second Stack', 'slug' => 'second-stack', 'sort_order' => 2, 'is_active' => true]);

        $this->post(route('admin.courses.store'), [
            'code' => 'WS-UPDATE-1', 'name' => 'Before Update', 'course_level_id' => $level->id,
            'stack_ids' => [$firstStack->id], 'supplement_ids' => [], 'language_ids' => [],
        ])->assertRedirect();
        $course = Course::where('code', 'WS-UPDATE-1')->firstOrFail();

        $this->put(route('admin.courses.update', $course), [
            'code' => 'WS-UPDATE-2', 'name' => 'After Update', 'course_level_id' => $level->id,
            'stack_ids' => [$secondStack->id], 'supplement_ids' => [], 'language_ids' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'code' => 'WS-UPDATE-2', 'name' => 'After Update']);
        $this->assertDatabaseMissing('course_stack', ['course_id' => $course->id, 'stack_id' => $firstStack->id]);
        $this->assertDatabaseHas('course_stack', ['course_id' => $course->id, 'stack_id' => $secondStack->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'course.updated', 'subject_id' => (string) $course->id]);
    }

    public function test_course_foreign_ids_and_duplicate_code_are_validated(): void
    {
        $payload = ['code' => 'WS-INVALID', 'name' => 'Invalid Course', 'course_level_id' => 999999, 'stack_ids' => [999999], 'supplement_ids' => [999999], 'language_ids' => [999999]];
        $this->post(route('admin.courses.store'), $payload)->assertSessionHasErrors(['course_level_id', 'stack_ids.0', 'supplement_ids.0', 'language_ids.0']);
        $this->assertDatabaseMissing('courses', ['code' => 'WS-INVALID']);

        $this->post(route('admin.courses.store'), ['code' => 'WS-DUP', 'name' => 'First Course'])->assertRedirect();
        $this->post(route('admin.courses.store'), ['code' => 'WS-DUP', 'name' => 'Second Course'])->assertSessionHasErrors('code');
    }

    public function test_non_admin_is_denied_provider_and_course_mutations(): void
    {
        $this->actingAs(User::factory()->student()->create())->get(route('admin.providers.index'))->assertForbidden();
        $this->actingAs(User::factory()->student()->create())->get(route('admin.courses.index'))->assertForbidden();
    }
}
