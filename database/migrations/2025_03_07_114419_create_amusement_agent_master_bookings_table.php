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
        Schema::create('amusement_agent_master_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->bigInteger('agent_id')->unsigned()->nullable();
            $table->bigInteger('attendee_id')->nullable();
            $table->string('booking_id')->nullable();
            $table->string('order_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
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
        Schema::dropIfExists('amusement_agent_master_bookings');
    }
};
