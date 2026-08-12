<?php

namespace Database\Seeders;

use App\Models\CourseLevel;
use App\Models\Language;
use App\Models\Role;
use App\Models\Stack;
use App\Models\Supplement;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([[Role::ADMIN, 'Admin', 'Full administrative access'], [Role::PROCTOR, 'Proctor', 'Assigned examination operations'], [Role::INSTRUCTOR, 'Instructor', 'Assigned class monitoring and requests'], [Role::STUDENT, 'Student', 'Assigned learning and assessment access']] as [$key, $name, $description]) {
            Role::updateOrCreate(['key' => $key], compact('name', 'description'));
        }

        foreach ([CourseLevel::class => ['Foundation', 'Advanced'], Stack::class => ['Well Control'], Supplement::class => ['Core'], Language::class => ['English']] as $model => $values) {
            foreach ($values as $name) {
                $model::updateOrCreate(['slug' => str($name)->slug()], ['name' => $name, 'sort_order' => 0, 'is_active' => true]);
            }
        }
    }
}
