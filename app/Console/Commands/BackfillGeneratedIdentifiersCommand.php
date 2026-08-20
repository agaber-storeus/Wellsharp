<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Services\ExamCodeGenerator;
use App\Services\QuestionCodeGenerator;
use App\Services\UserIdentityGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent, explicit-run-only backfill for identifiers introduced after
 * existing rows were created: users.username and questions.code (exams.code
 * predates this command and may also be blank on old rows). Never runs
 * automatically - existing WellSharp IDs/usernames/codes are never touched,
 * only rows where the column is currently null get one assigned.
 *
 * questions.code is NOT NULL at the database level (see migration
 * 2026_08_20_000001, which backfills+enforces it in one safe step), so the
 * "missing question code" branch below can never find a row in normal
 * operation - it's kept as a harmless no-op defense-in-depth check, not a
 * required deployment step.
 */
class BackfillGeneratedIdentifiersCommand extends Command
{
    protected $signature = 'wellsharp:backfill-identifiers {--dry-run : Report what would change without saving}';

    protected $description = 'Assign generated username/exam code/question code to existing rows missing one';

    public function handle(UserIdentityGenerator $identity, ExamCodeGenerator $examCodes, QuestionCodeGenerator $questionCodes): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $missingUsernames = User::query()->whereNull('username')->with('profile')->get();
        $missingExamCodes = Exam::query()->whereNull('code')->get();
        $missingQuestionCodes = Question::query()->whereNull('code')->get();

        $this->info("Users missing username: {$missingUsernames->count()}");
        $this->info("Exams missing code: {$missingExamCodes->count()}");
        $this->info("Questions missing code: {$missingQuestionCodes->count()}");

        if ($dryRun) {
            $this->comment('Dry run: no changes saved.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($missingUsernames, $missingExamCodes, $missingQuestionCodes, $identity, $examCodes, $questionCodes): void {
            foreach ($missingUsernames as $user) {
                $user->forceFill([
                    'username' => $identity->generateUsername($user->profile?->first_name ?? '', $user->profile?->last_name ?? ''),
                ])->save();
            }
            foreach ($missingExamCodes as $exam) {
                $exam->forceFill(['code' => $examCodes->generate()])->save();
            }
            foreach ($missingQuestionCodes as $question) {
                $question->forceFill(['code' => $questionCodes->generate()])->save();
            }
        });

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
