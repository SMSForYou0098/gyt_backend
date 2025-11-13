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
        Schema::create('amusement_master_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->text('session_id')->nullable();
            $table->string('agent_id', 30)->nullable();
            $table->string('booking_id')->nullable();
            $table->string('order_id')->nullable();
            $table->string('amount', 30)->nullable();
            $table->string('discount', 30)->nullable();
            $table->string('payment_method', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amusement_master_bookings');
    }
};
