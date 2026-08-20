<?php

use App\Models\Question;
use App\Services\QuestionCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Safe on a populated database: backfill every NULL questions.code first
     * (through QuestionCodeGenerator, the same collision-checked generator
     * every other creation path uses - not raw SQL randomness), verify zero
     * NULLs remain, then enforce NOT NULL. Aborts rather than leaving a
     * partially-applied constraint if backfill can't finish for any row.
     */
    public function up(): void
    {
        $generator = app(QuestionCodeGenerator::class);

        Question::query()->whereNull('code')->orderBy('id')->chunkById(200, function ($questions) use ($generator): void {
            foreach ($questions as $question) {
                $question->forceFill(['code' => $generator->generate()])->save();
            }
        });

        $remaining = Question::query()->whereNull('code')->count();
        if ($remaining > 0) {
            throw new RuntimeException("Refusing to enforce NOT NULL on questions.code: {$remaining} row(s) still missing a code.");
        }

        Schema::table('questions', function (Blueprint $table): void {
            $table->string('code', 5)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->string('code', 5)->nullable()->change();
        });
    }
};
