<?php

namespace App\Http\Requests\Admin;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'question_text' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'in:true_false,mcq,input'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'default_marks' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'question_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'correct_answer_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'correct_answer' => ['nullable', 'string', 'max:1000'],
            'solution_text' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array', 'max:4'],
            'options.*' => ['nullable', 'string', 'max:1000'],
            'option_images' => ['nullable', 'array', 'max:4'],
            'option_images.*' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'remove_question_image' => ['nullable', 'boolean'],
            'remove_correct_answer_image' => ['nullable', 'boolean'],
            'remove_option_images' => ['nullable', 'array'],
            'correct_option' => ['nullable', 'integer', 'min:0', 'max:3'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('type');
            $answer = trim((string) $this->input('correct_answer'));
            $options = array_values(array_filter(array_map(fn ($option): string => trim((string) $option), $this->input('options', [])), fn (string $option): bool => $option !== ''));

            if ($type === 'true_false' && ! in_array(strtolower($answer), ['true', 'false'], true)) {
                $validator->errors()->add('correct_answer', 'Choose True or False.');
            }
            if ($type === 'input' && $answer === '') {
                $validator->errors()->add('correct_answer', 'A correct answer is required.');
            }
            if ($type === 'mcq') {
                if (count($options) < 2) {
                    $validator->errors()->add('options', 'Multiple-choice questions require at least two options.');
                }
                $correct = $this->input('correct_option');
                if ($correct === null || ! array_key_exists((int) $correct, $options)) {
                    $validator->errors()->add('correct_option', 'Choose one valid correct option.');
                }
            }

            $course = $this->route('course');
            if ($course && $this->filled('question_text')) {
                $normalized = Question::normalizeText($this->input('question_text'));
                $query = $course->questions();
                if ($this->route('question')) {
                    $query->where('id', '!=', $this->route('question')->getKey());
                }
                if ($query->get()->contains(fn (Question $question): bool => Question::normalizeText($question->question_text) === $normalized)) {
                    $validator->errors()->add('question_text', 'This question already exists in this course.');
                }
            }
        });
    }
}
