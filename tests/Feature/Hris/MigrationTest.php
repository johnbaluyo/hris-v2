<?php

namespace Tests\Feature\Hris;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_new_module_migrations_roll_back_and_reapply_on_an_isolated_database(): void
    {
        $this->assertTrue(Schema::hasTable('daily_attendances'));
        $steps = DB::table('migrations')->where('migration', '>=', '2026_10_21_000001')->count();
        $this->assertTrue(Schema::hasTable('exam_attempts'));
        $this->assertTrue(Schema::hasTable('notification_receipts'));
        $this->assertTrue(Schema::hasTable('raw_punch_revisions'));
        $this->assertTrue(Schema::hasColumn('raw_punches', 'source_evidence'));
        $this->artisan('migrate:rollback', ['--step' => $steps, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('exam_attempts'));
        $this->assertFalse(Schema::hasTable('notification_receipts'));
        $this->assertFalse(Schema::hasTable('raw_punch_revisions'));
        $this->assertFalse(Schema::hasTable('daily_attendances'));
        $this->assertFalse(Schema::hasTable('divisions'));
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('daily_attendances'));
        $this->assertTrue(Schema::hasTable('pds_entries'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasTable('exam_attempts'));
        $this->assertTrue(Schema::hasTable('notification_receipts'));
        $this->assertTrue(Schema::hasTable('raw_punch_revisions'));
        $this->assertTrue(Schema::hasColumn('raw_punches', 'lock_version'));
    }
}
