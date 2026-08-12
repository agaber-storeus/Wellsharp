<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->admin()->create();
        $this->course = Course::factory()->create();
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
    }

    public function test_admin_can_create_each_question_type_and_mcq_options_are_relational(): void
    {
        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => 'Is the barrier verified?', 'type' => 'true_false', 'difficulty' => 'easy', 'correct_answer' => 'TRUE',
        ])->assertRedirect();
        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => 'Choose the primary barrier.', 'type' => 'mcq', 'difficulty' => 'medium',
            'options' => ['BOP', 'Mud', 'Casing'], 'correct_option' => 0,
        ])->assertRedirect();
        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => 'Name the barrier.', 'type' => 'input', 'difficulty' => 'hard', 'correct_answer' => 'BOP',
        ])->assertRedirect();

        $this->assertDatabaseHas('questions', ['course_id' => $this->course->id, 'type' => 'true_false', 'correct_answer_boolean' => 1]);
        $this->assertDatabaseHas('questions', ['course_id' => $this->course->id, 'type' => 'input', 'correct_answer_text' => 'BOP']);
        $mcq = Question::where('type', 'mcq')->firstOrFail();
        $this->assertCount(3, $mcq->options);
        $this->assertSame('BOP', $mcq->options->firstWhere('is_correct', true)->option_text);
        $this->assertDatabaseHas('audit_events', ['action' => 'question.created']);
    }

    public function test_admin_question_views_render_type_adaptive_forms(): void
    {
        $this->get(route('admin.courses.questions.index', $this->course))->assertOk()->assertSee('Question bank');
        $this->get(route('admin.courses.questions.create', $this->course))
            ->assertOk()
            ->assertSee('Add option')
            ->assertSee('Correct answer')
            ->assertSee('Create question')
            ->assertSee('admin-question-wizard.css')
            ->assertSee('var(--admin-white)')
            ->assertSee('name="question_image"', false)
            ->assertSee('name="option_images[]"', false)
            ->assertDontSee('Default time (minutes)')
            ->assertDontSee('default_time_minutes')
            ->assertDontSee('#0f172a');
        $this->get(route('admin.courses.questions.import', $this->course))->assertOk()->assertSee('Download Excel template');
    }

    public function test_question_time_is_not_persisted_and_schedule_owns_exam_duration(): void
    {
        $this->assertFalse(Schema::hasColumn('questions', 'default_time_minutes'));

        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => 'How is exam time allocated?', 'type' => 'input', 'difficulty' => 'easy',
            'correct_answer' => 'Per scheduled exam attempt', 'default_time_minutes' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('questions', ['question_text' => 'How is exam time allocated?']);
    }

    public function test_question_and_mcq_answer_images_are_converted_to_webp_and_saved(): void
    {
        Storage::fake('public');

        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => 'Which barrier is shown?', 'type' => 'mcq', 'difficulty' => 'medium',
            'options' => ['Primary barrier', 'Secondary barrier', 'No barrier'], 'correct_option' => 0,
            'question_image' => UploadedFile::fake()->image('question.png', 80, 60),
            'option_images' => [
                UploadedFile::fake()->image('primary.png', 80, 60),
                UploadedFile::fake()->image('secondary.png', 80, 60),
                UploadedFile::fake()->image('none.png', 80, 60),
            ],
        ])->assertRedirect();

        $question = Question::query()->where('question_text', 'Which barrier is shown?')->with('options')->firstOrFail();
        $this->assertStringEndsWith('.webp', $question->question_image_path);
        Storage::disk('public')->assertExists($question->question_image_path);
        $this->assertCount(3, $question->options);
        foreach ($question->options as $option) {
            $this->assertStringEndsWith('.webp', $option->image_path);
            Storage::disk('public')->assertExists($option->image_path);
        }

        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => 'Enter the illustrated answer.', 'type' => 'input', 'difficulty' => 'hard',
            'correct_answer' => 'BOP',
            'correct_answer_image' => UploadedFile::fake()->image('answer.jpg', 80, 60),
        ])->assertRedirect();
        $inputQuestion = Question::query()->where('question_text', 'Enter the illustrated answer.')->firstOrFail();
        $this->assertStringEndsWith('.webp', $inputQuestion->correct_answer_image_path);
        Storage::disk('public')->assertExists($inputQuestion->correct_answer_image_path);
    }

    public function test_manual_validation_rejects_duplicates_and_invalid_mcq_answers(): void
    {
        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => '  Same   question ', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer' => 'answer',
        ])->assertRedirect();
        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => 'same question', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer' => 'answer',
        ])->assertSessionHasErrors('question_text');
        $this->post(route('admin.courses.questions.store', $this->course), [
            'question_text' => 'Invalid choices', 'type' => 'mcq', 'difficulty' => 'easy',
            'options' => ['One'], 'correct_option' => 1,
        ])->assertSessionHasErrors(['options', 'correct_option']);
        $this->assertCount(1, Question::all());
    }

    public function test_question_bank_is_admin_only_and_wrong_course_is_not_accessible(): void
    {
        $student = User::factory()->student()->create();
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])->get(route('admin.courses.questions.index', $this->course))->assertForbidden();

        $otherCourse = Course::factory()->create();
        $question = Question::create(['course_id' => $this->course->id, 'question_text' => 'Protected', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'secret']);
        $this->actingAs($this->admin)->withSession(['auth.session_version' => $this->admin->session_version]);
        $this->get(route('admin.courses.questions.edit', [$otherCourse, $question]))->assertNotFound();
        $this->get(route('admin.courses.questions.index', $this->course))->assertOk()->assertDontSee('secret');
    }

    public function test_admin_can_archive_a_question_and_audit_it(): void
    {
        $question = Question::create(['course_id' => $this->course->id, 'question_text' => 'Archive me', 'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'answer']);
        $this->delete(route('admin.courses.questions.destroy', [$this->course, $question]))->assertRedirect();
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'is_active' => 0]);
        $this->assertDatabaseHas('audit_events', ['action' => 'question.archived']);
    }

    public function test_template_is_available_only_to_admin(): void
    {
        if (! class_exists('ZipArchive')) {
            $this->markTestSkipped('PHP ext-zip is required to generate XLSX files.');
        }
        $this->get(route('admin.courses.questions.template', $this->course))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $student = User::factory()->student()->create();
        $this->actingAs($student)->withSession(['auth.session_version' => $student->session_version])->get(route('admin.courses.questions.template', $this->course))->assertForbidden();
    }

    public function test_import_validates_then_imports_atomically_and_audits_without_raw_answers(): void
    {
        $file = $this->spreadsheetFile([
            ['question', 'type', 'difficulty', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'],
            ['Imported choice', 'mcq', 'medium', 'First', 'Second', '', '', 'B'],
            ['Imported truth', 'true_false', 'easy', '', '', '', '', 'yes'],
        ]);
        $this->post(route('admin.courses.questions.import.preview', $this->course), ['questions_file' => $file])->assertRedirect();
        $this->get(route('admin.courses.questions.import', $this->course))->assertOk()->assertSee('2 valid')->assertSee('Imported choice');
        $this->post(route('admin.courses.questions.import.confirm', $this->course))->assertRedirect();

        $this->assertDatabaseCount('questions', 2);
        $this->assertDatabaseCount('question_options', 2);
        $this->assertDatabaseHas('questions', ['question_text' => 'Imported truth', 'correct_answer_boolean' => 1]);
        $this->assertDatabaseHas('audit_events', ['action' => 'questions.imported']);
        $this->assertDatabaseMissing('audit_events', ['after_state->filename' => '']);
    }

    public function test_invalid_import_preview_blocks_all_writes(): void
    {
        $file = $this->spreadsheetFile([
            ['question', 'type', 'difficulty', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'],
            ['Good row', 'input', 'easy', '', '', '', '', 'Answer'],
            ['Bad row', 'unknown', 'impossible', '', '', '', '', 'Answer'],
        ]);
        $this->post(route('admin.courses.questions.import.preview', $this->course), ['questions_file' => $file])->assertRedirect();
        $this->get(route('admin.courses.questions.import', $this->course))->assertSee('1 valid')->assertSee('1 invalid');
        $this->post(route('admin.courses.questions.import.confirm', $this->course))->assertSessionHasErrors('questions_file');
        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseMissing('audit_events', ['action' => 'questions.imported']);
    }

    public function test_import_rejects_wrong_extension_and_missing_columns(): void
    {
        $this->post(route('admin.courses.questions.import.preview', $this->course), [
            'questions_file' => UploadedFile::fake()->createWithContent('questions.txt', 'not a spreadsheet'),
        ])->assertSessionHasErrors('questions_file');

        $this->post(route('admin.courses.questions.import.preview', $this->course), [
            'questions_file' => UploadedFile::fake()->createWithContent('questions.csv', "question,type\nMissing columns,input"),
        ])->assertSessionHasErrors('questions_file');
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_import_rejects_sparse_mcq_option_columns_before_saving(): void
    {
        $file = $this->spreadsheetFile([
            ['question', 'type', 'difficulty', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'],
            ['Sparse options', 'mcq', 'easy', '', 'Yes', 'No', '', 'B'],
        ]);

        $this->post(route('admin.courses.questions.import.preview', $this->course), ['questions_file' => $file])->assertRedirect();
        $this->get(route('admin.courses.questions.import', $this->course))
            ->assertSee('0 valid')
            ->assertSee('1 invalid')
            ->assertSee('option columns must be filled in order');
        $this->post(route('admin.courses.questions.import.confirm', $this->course))->assertSessionHasErrors('questions_file');
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_import_accepts_a_utf8_bom_in_the_first_header(): void
    {
        $file = $this->spreadsheetFile([
            ["\xEF\xBB\xBFquestion", 'type', 'difficulty', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'],
            ['BOM question', 'input', 'easy', '', '', '', '', 'Answer'],
        ]);

        $this->post(route('admin.courses.questions.import.preview', $this->course), ['questions_file' => $file])->assertRedirect();
        $this->get(route('admin.courses.questions.import', $this->course))->assertSee('1 valid')->assertSee('BOM question');
        $this->post(route('admin.courses.questions.import.confirm', $this->course))->assertRedirect();

        $this->assertDatabaseHas('questions', ['question_text' => 'BOM question']);
    }

    public function test_correct_answers_are_hidden_from_default_model_serialization(): void
    {
        $question = Question::create([
            'course_id' => $this->course->id, 'question_text' => 'Do not expose this answer',
            'type' => 'input', 'difficulty' => 'easy', 'correct_answer_text' => 'private answer',
        ]);
        $this->assertStringNotContainsString('private answer', json_encode($question));
    }

    private function spreadsheetFile(array $rows): UploadedFile
    {
        $csv = implode("\n", array_map(fn (array $row): string => implode(',', array_map(fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"', $row)), $rows));

        return UploadedFile::fake()->createWithContent('questions.csv', $csv);
    }
}
