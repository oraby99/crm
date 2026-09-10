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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->index();
            $table->string('whatsapp_phone', 30)->nullable();
            $table->foreignId('platform_id')->nullable()->constrained('platforms')->nullOnDelete();
            $table->foreignId('customer_need_id')->nullable()->constrained('customer_needs')->nullOnDelete();
            $table->text('details')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('customer_statuses')->nullOnDelete();
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sales_id');
            $table->index('team_leader_id');
            $table->index('status_id');
            $table->index('platform_id');
            $table->index('customer_need_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
