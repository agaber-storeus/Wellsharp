<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('certificates')->select(['id', 'issued_at'])->orderBy('id')->chunkById(500, function ($certificates): void {
            $rows = [];
            foreach ($certificates as $certificate) {
                if (DB::table('certificate_documents')->where('certificate_id', $certificate->id)->where('type', 'full_certificate')->exists()) continue;
                $rows[] = ['public_id' => (string) Str::ulid(), 'certificate_id' => $certificate->id, 'type' => 'full_certificate', 'title' => 'Full Certificate', 'path' => null, 'issued_at' => $certificate->issued_at, 'created_at' => now(), 'updated_at' => now()];
            }
            if ($rows !== []) DB::table('certificate_documents')->insert($rows);
        });
    }

    public function down(): void
    {
        DB::table('certificate_documents')->where('type', 'full_certificate')->delete();
    }
};
