<?php

namespace Tests\Feature\Hris;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_new_module_migrations_roll_back_and_reapply_on_an_isolated_database(): void
    {
        $this->assertTrue(Schema::hasTable('daily_attendances'));
        $this->artisan('migrate:rollback', ['--step' => 9, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('daily_attendances'));
        $this->assertFalse(Schema::hasTable('divisions'));
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('daily_attendances'));
        $this->assertTrue(Schema::hasTable('pds_entries'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
    }
}
