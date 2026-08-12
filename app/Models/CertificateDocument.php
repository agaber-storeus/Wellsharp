<?php

namespace App\Models;

use App\Enums\CertificateDocumentType;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateDocument extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['certificate_id', 'type', 'title', 'path', 'issued_at'];

    protected function casts(): array
    {
        return [
            'type' => CertificateDocumentType::class,
            'issued_at' => 'datetime',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
