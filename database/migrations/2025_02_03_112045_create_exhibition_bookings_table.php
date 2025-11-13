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
        Schema::create('exhibition_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('token');
            $table->string('agent_id')->nullable();
            $table->string('ticket_id')->nullable();
            $table->string('attendee_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('quantity')->nullable();
            $table->string('discount')->nullable();
            $table->string('amount')->nullable();
            $table->string('type')->nullable();
            $table->dateTime('date')->nullable();
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
        Schema::dropIfExists('exhibition_bookings');
    }
};
