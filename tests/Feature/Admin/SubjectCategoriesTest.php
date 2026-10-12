<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\SubjectCategory;
use App\Models\TrainingClass;
use App\Models\User;
use Database\Seeders\IadcExamQuestions2026Seeder;
use Database\Seeders\SubjectCategoriesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubjectCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['auth.session_version' => $admin->session_version]);
    }

    public function test_exact_iadc_mapping_and_repeat_backfill_preserve_manual_assignments_and_legacy_courses(): void
    {
        foreach (IadcExamQuestions2026Seeder::legacySubjects() as $subject) {
            Course::factory()->create(['code' => $subject['code'], 'name' => $subject['name']]);
        }
        $legacy = Course::factory()->create(['code' => 'legacy-subject']);
        $before = Course::orderBy('id')->get(['id', 'public_id', 'code', 'name', 'updated_at'])->toArray();
        $this->seed(SubjectCategoriesSeeder::class);
        $drilling = SubjectCategory::where('code', 'drilling-operations')->firstOrFail();
        $servicing = SubjectCategory::where('code', 'well-servicing')->firstOrFail();
        $this->assertSame(6, $drilling->courses()->count());
        $this->assertSame(5, $servicing->courses()->count());
        $expected = [
            'drilling-operations' => ['drilling-operation-driller-surface', 'drilling-operation-supervisor-surface', 'drilling-operation-introductory', 'driller-kill-sheet', 'supervisor-kill-sheet', 'instructor-drilling-operations-supervisor-surface'],
            'well-servicing' => ['well-servicing-coiled-tubing', 'well-servicing-wireline', 'well-servicing-workover', 'well-servicing-ogor', 'instructor-well-servicing-workover'],
        ];
        foreach ($expected as $code => $codes) {
            $this->assertEqualsCanonicalizing($codes, SubjectCategory::where('code', $code)->firstOrFail()->courses()->pluck('code')->all());
        }
        $this->assertNull($legacy->fresh()->subjectCategory);
        $this->assertSame($before, Course::orderBy('id')->get(['id', 'public_id', 'code', 'name', 'updated_at'])->toArray());
        $manual = Course::where('code', 'driller-kill-sheet')->firstOrFail();
        $manual->update(['subject_category_id' => $servicing->id]);
        $snapshot = Course::orderBy('id')->get()->toArray();
        $this->seed(SubjectCategoriesSeeder::class);
        $this->assertDatabaseCount('subject_categories', 2);
        $this->assertSame($snapshot, Course::orderBy('id')->get()->toArray());
        $this->assertTrue($manual->fresh()->subjectCategory->is($servicing));
    }

    public function test_admin_create_update_optional_assignment_and_validation(): void
    {
        $category = SubjectCategory::firstOrFail();
        $payload = ['code' => 'CATEGORY-CRUD', 'name' => 'Category Subject', 'subject_category_id' => $category->id];
        $this->post(route('admin.courses.store'), $payload)->assertRedirect();
        $course = Course::where('code', $payload['code'])->firstOrFail();
        $this->assertTrue($course->subjectCategory->is($category));
        $this->get(route('admin.courses.show', $course))->assertOk()->assertSee('Category: '.$category->name);
        $this->get(route('admin.courses.edit', $course))->assertOk()->assertSee('name="subject_category_id"', false);
        $this->put(route('admin.courses.update', $course), ['code' => $course->code, 'name' => 'Legacy client update'])->assertRedirect();
        $this->assertEquals($category->id, $course->fresh()->subject_category_id);
        $this->put(route('admin.courses.update', $course), [...$payload, 'subject_category_id' => 999999])->assertSessionHasErrors('subject_category_id');
        $this->put(route('admin.courses.update', $course), [...$payload, 'subject_category_id' => null])->assertRedirect();
        $this->assertNull($course->fresh()->subject_category_id);
        $this->post(route('admin.courses.store'), ['code' => 'INVALID-CATEGORY', 'name' => 'Invalid', 'subject_category_id' => 999999])->assertSessionHasErrors('subject_category_id');
        $this->post(route('admin.courses.store'), ['code' => 'UNCATEGORIZED', 'name' => 'Legacy'])->assertRedirect();
        $this->assertDatabaseHas('courses', ['code' => 'UNCATEGORIZED', 'subject_category_id' => null]);
    }

    public function test_subject_filters_and_group_order_work_with_existing_sorting_and_pagination(): void
    {
        $drilling = SubjectCategory::where('code', 'drilling-operations')->firstOrFail();
        $servicing = SubjectCategory::where('code', 'well-servicing')->firstOrFail();
        Course::factory()->create(['name' => 'Z drilling', 'subject_category_id' => $drilling->id]);
        Course::factory()->create(['name' => 'A servicing', 'subject_category_id' => $servicing->id]);
        Course::factory()->create(['name' => 'Legacy subject']);
        $this->getJson(route('admin.subjects.data', ['subject_category_id' => $drilling->id]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', 'Drilling Operations');
        $this->getJson(route('admin.subjects.data', ['subject_category_id' => 'uncategorized']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Legacy subject');
        $this->getJson(route('admin.subjects.data', ['group_by_category' => 1, 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()->assertJsonPath('data.0.name', 'Z drilling')->assertJsonPath('data.1.name', 'A servicing')->assertJsonPath('data.2.category', 'Uncategorized');
        $this->get(route('admin.subjects.index', ['group_by_category' => 1, 'sort' => 'level']))
            ->assertOk()->assertSee('Group by category')->assertSee('All Categories')->assertSee('Uncategorized');
    }

    public function test_admin_selectors_group_subjects_without_changing_ids_or_selected_values(): void
    {
        $category = SubjectCategory::where('code', 'drilling-operations')->firstOrFail();
        $course = Course::factory()->create(['subject_category_id' => $category->id]);
        $legacy = Course::factory()->create();
        $exam = Exam::factory()->published()->create(['course_id' => $course->id]);
        Question::factory()->trueFalse()->create(['course_id' => $course->id]);
        foreach (['admin.exams.create', 'admin.questions.create', 'admin.questions.import', 'admin.classes.create'] as $route) {
            $this->get(route($route, ['course_id' => $course->id]))->assertOk()
                ->assertSee('<optgroup label="Drilling Operations">', false)
                ->assertSee('<optgroup label="Uncategorized">', false)
                ->assertSee('value="'.$legacy->id.'"', false);
        }
        $this->get(route('admin.exam-schedules.create', ['exam_id' => $exam->id]))->assertOk()
            ->assertSee('<optgroup label="Drilling Operations">', false);
        $html = view('components.admin.subject-options', ['subjects' => collect([$course, $legacy]), 'selected' => $legacy->id])->render();
        $this->assertMatchesRegularExpression('/value="'.$legacy->id.'"\s+selected/', $html);
        $this->get(route('admin.exams.edit', $exam))->assertOk()->assertSee('value="'.$course->id.'" selected', false);
    }

    public function test_category_changes_preserve_exam_and_question_relationships_and_archived_selections(): void
    {
        $course = Course::factory()->create();
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        $question = Question::factory()->trueFalse()->create(['course_id' => $course->id]);
        $class = TrainingClass::factory()->create(['course_id' => $course->id]);
        $this->put(route('admin.courses.update', $course), ['code' => $course->code, 'name' => $course->name, 'subject_category_id' => SubjectCategory::firstOrFail()->id])->assertRedirect();
        $this->assertTrue($exam->fresh()->subject->is($course));
        $this->assertTrue($question->fresh()->course->is($course));
        $this->assertSame($course->id, $class->fresh()->course_id);
        $course->update(['status' => 'retired', 'archived_at' => now()]);
        $this->get(route('admin.exams.edit', $exam))->assertOk()->assertSee('value="'.$course->id.'" selected', false);
        $this->get(route('admin.classes.edit', $class))->assertOk()->assertSee('value="'.$course->id.'" selected', false);
    }

    public function test_category_deletion_keeps_course_and_nulls_optional_foreign_key(): void
    {
        $category = SubjectCategory::firstOrFail();
        $course = Course::factory()->create(['subject_category_id' => $category->id]);
        $category->delete();
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'subject_category_id' => null]);
    }

    public function test_database_rejects_invalid_category_foreign_key(): void
    {
        $course = Course::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('courses')->where('id', $course->id)->update(['subject_category_id' => 999999]);
    }

    public function test_category_codes_are_unique(): void
    {
        $category = SubjectCategory::firstOrFail();
        $this->expectException(QueryException::class);
        SubjectCategory::create(['code' => $category->code, 'name' => 'Duplicate']);
    }

    public function test_admin_filters_render_accessible_names_and_grouping_label(): void
    {
        foreach (['admin.subjects.index', 'admin.exams.index', 'admin.questions.index', 'admin.classes.index', 'admin.exam-schedules.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('aria-label="Search"', false)->assertSee('aria-label="Status"', false);
        }
        $this->get(route('admin.subjects.index'))->assertOk()
            ->assertSee('class="filter-checkbox" for="group-by-category"', false)
            ->assertSee('id="group-by-category" type="checkbox"', false)
            ->assertSee('aria-label="Category"', false);
    }

    public function test_subject_validation_feedback_and_long_category_names_render_safely(): void
    {
        $category = SubjectCategory::create(['code' => 'long-label', 'name' => str_repeat('Long category ', 10).' <special>']);
        $this->get(route('admin.courses.create'))->assertOk()->assertSee(e($category->name), false);
        $this->from(route('admin.courses.create'))->post(route('admin.courses.store'), ['code' => 'VALIDATION-UI', 'name' => '', 'subject_category_id' => $category->id])->assertSessionHasErrors('name');
        $this->get(route('admin.courses.create'))->assertOk()->assertSee('role="alert"', false)
            ->assertSee('value="'.$category->id.'" selected', false);
    }
}
