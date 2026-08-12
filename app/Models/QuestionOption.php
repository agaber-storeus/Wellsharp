<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    use HasFactory, HasPublicUlid;

    protected $hidden = ['is_correct'];

    protected $fillable = ['question_id', 'option_text', 'image_path', 'is_correct', 'display_order'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'display_order' => 'integer'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
