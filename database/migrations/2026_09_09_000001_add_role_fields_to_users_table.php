<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('sales')->after('email');
            $table->foreignId('team_leader_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('team_leader_id');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_leader_id']);
            $table->dropColumn(['role', 'team_leader_id', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
