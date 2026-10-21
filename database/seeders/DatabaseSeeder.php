<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Provision accounts using hris:create-administrator and HR invitations.
        // Factories are reserved for isolated automated tests; no shared credentials are seeded.
    }
}
