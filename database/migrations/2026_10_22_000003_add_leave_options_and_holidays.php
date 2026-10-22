<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->boolean('for_disapproval')->default(false)->after('commutation');
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('holiday_date')->index();
            $table->string('type', 40)->default('Regular');
            $table->timestamps();
            $table->unique(['holiday_date', 'name']);
        });
        DB::table('leave_types')->where('code', 'FL')->update(['name' => 'Mandatory/Forced Leave']);
        DB::table('leave_types')->insertOrIgnore([
            'id' => 15, 'code' => 'STUDY', 'name' => 'Study Leave', 'accumulation' => 'yearly', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::table('leave_requests', fn (Blueprint $table) => $table->dropColumn('for_disapproval'));
        DB::table('leave_types')->where('code', 'STUDY')->where('id', 15)->delete();
        DB::table('leave_types')->where('code', 'FL')->update(['name' => 'Forced Leave']);
    }
};
