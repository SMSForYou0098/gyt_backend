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
        Schema::create('pos_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->string('user_id');
            $table->string('ticket_id')->nullable();
            $table->string('name')->nullable();
            $table->string('number')->nullable();
            $table->string('quantity')->nullable();
            $table->string('discount')->nullable();
            $table->string('amount')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_bookings');
    }
};
