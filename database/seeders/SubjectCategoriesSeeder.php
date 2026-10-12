<?php

namespace Database\Seeders;

use App\Models\SubjectCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectCategoriesSeeder extends Seeder
{
    public const SUBJECT_CODES = [
        'drilling-operations' => [
            'drilling-operation-driller-surface',
            'drilling-operation-supervisor-surface',
            'drilling-operation-introductory',
            'driller-kill-sheet',
            'supervisor-kill-sheet',
            'instructor-drilling-operations-supervisor-surface',
        ],
        'well-servicing' => [
            'well-servicing-coiled-tubing',
            'well-servicing-wireline',
            'well-servicing-workover',
            'well-servicing-ogor',
            'instructor-well-servicing-workover',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['drilling-operations' => 'Drilling Operations', 'well-servicing' => 'Well Servicing'] as $code => $name) {
                $category = SubjectCategory::firstOrCreate(['code' => $code], ['name' => $name]);
                DB::table('courses')->whereNull('subject_category_id')
                    ->whereIn('code', self::SUBJECT_CODES[$code])
                    ->update(['subject_category_id' => $category->id]);
            }
        });
    }
}
