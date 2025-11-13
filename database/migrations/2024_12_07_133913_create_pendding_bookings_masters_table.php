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
        Schema::create('pendding_bookings_masters', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->text('session_id')->nullable();
            $table->string('agent_id')->nullable();
            $table->string('booking_id')->nullable();
            $table->string('order_id')->nullable();
            $table->string('amount')->nullable();
            $table->string('discount')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendding_bookings_masters');
    }
};
