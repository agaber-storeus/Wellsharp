<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSurveyAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['student_survey_id', 'question_key', 'answer'];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(StudentSurvey::class, 'student_survey_id');
    }
}
