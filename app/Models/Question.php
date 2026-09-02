<?php

namespace App\Models;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Concerns\HasPublicUlid;
use App\Services\QuestionCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory, HasPublicUlid;

    /**
     * Question codes are generated once on creation, never touched on
     * update, so they stay stable while question text/answers are edited.
     */
    protected static function booted(): void
    {
        static::saving(function (Question $question): void {
            if ($question->course_id !== null && filled($question->question_text)) {
                $question->question_text_hash = self::textHash($question->question_text);
            }
        });

        static::creating(function (Question $question): void {
            if (blank($question->code)) {
                $question->code = app(QuestionCodeGenerator::class)->generate();
            }
        });
    }

    protected $hidden = ['correct_answer_text', 'correct_answer_boolean', 'correct_answer_image_path'];

    protected $fillable = [
        'course_id', 'code', 'question_text', 'question_text_hash', 'question_image_path', 'type', 'difficulty', 'default_marks',
        'correct_answer_image_path',
        'correct_answer_text', 'correct_answer_boolean', 'solution_text',
        'is_active', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => QuestionDifficulty::class,
            'correct_answer_boolean' => 'boolean',
            'default_marks' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('display_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_questions')
            ->withPivot(['display_order', 'points'])
            ->orderBy('exam_questions.display_order');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function normalizeText(?string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $text)));
    }

    public static function textHash(?string $text): string
    {
        return hash('sha256', self::normalizeText($text));
    }

    /** Clean imported Word HTML for question lists, reports, and exam screens. */
    public function getDisplayQuestionTextAttribute(): string
    {
        $text = html_entity_decode((string) $this->question_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
