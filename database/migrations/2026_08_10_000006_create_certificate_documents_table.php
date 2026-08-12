<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_documents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('certificate_id')->constrained('certificates')->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('title', 180);
            $table->string('path')->nullable();
            $table->timestampTz('issued_at');
            $table->timestampsTz();
            $table->unique(['certificate_id', 'type']);
            $table->index(['type', 'issued_at']);
        });

        $types = [
            'knowledge_assessment_report' => 'Knowledge Assessment Report',
            'completion_card_front' => 'Completion Card - Front',
            'completion_card_back' => 'Completion Card - Back',
        ];
        DB::table('certificates')->select(['id', 'issued_at'])->orderBy('id')->chunkById(500, function ($certificates) use ($types): void {
            $rows = [];
            foreach ($certificates as $certificate) {
                foreach ($types as $type => $title) {
                    $rows[] = [
                        'public_id' => (string) Str::ulid(),
                        'certificate_id' => $certificate->id,
                        'type' => $type,
                        'title' => $title,
                        'path' => null,
                        'issued_at' => $certificate->issued_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if ($rows !== []) {
                DB::table('certificate_documents')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_documents');
    }
};
