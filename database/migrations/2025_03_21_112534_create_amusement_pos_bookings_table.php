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
        Schema::create('amusement_pos_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->string('user_id');
            $table->string('ticket_id')->nullable();
            $table->string('name')->nullable();
            $table->string('number')->nullable();
            $table->string('quantity')->nullable();
            $table->string('discount')->nullable();
            $table->string('amount')->nullable();
            $table->string('convenience_fee')->nullable();
            $table->string('base_amount')->nullable();
            $table->string('payment_method')->nullable();
            $table->boolean('is_scaned')->nullable();
            $table->timestamp('booking_date')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amusement_pos_bookings');
    }
};
