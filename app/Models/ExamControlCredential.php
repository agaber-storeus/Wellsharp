<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamControlCredential extends Model
{
    protected $fillable = ['user_id', 'control_id'];

    protected $hidden = ['control_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
