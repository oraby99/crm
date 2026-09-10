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
        Schema::create('customer_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_type');
            $table->foreignId('old_status_id')->nullable()->constrained('customer_statuses')->nullOnDelete();
            $table->foreignId('new_status_id')->nullable()->constrained('customer_statuses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('follow_up_date')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_activities');
    }
};
