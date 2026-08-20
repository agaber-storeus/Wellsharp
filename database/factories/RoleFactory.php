<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The application only ever operates with the 4 fixed roles created by
 * DatabaseSeeder (admin/proctor/instructor/student) - prefer looking those
 * up over building ad hoc ones. This factory exists only so relations that
 * require a Role (e.g. RoleAssignmentFactory) have a safe fallback when a
 * test runs without seeding roles first.
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->regexify('[a-z]{8}'),
            'name' => ucfirst(fake()->unique()->word()),
            'description' => null,
        ];
    }
}
