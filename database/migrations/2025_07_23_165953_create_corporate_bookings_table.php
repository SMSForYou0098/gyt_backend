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
        Schema::create('corporate_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->string('user_id');
            $table->string('ticket_id')->nullable();
            $table->string('name')->nullable();
            $table->string('number')->nullable();
            $table->string('email')->nullable();
            $table->string('quantity')->nullable();
            $table->decimal('discount', 30, 0)->nullable()->default(0);
            $table->decimal('amount', 30, 0)->nullable()->default(0);
            $table->decimal('convenience_fee', 30, 0)->nullable()->default(0);
            $table->string('base_amount', 10)->nullable();
            $table->string('status');
            $table->string('payment_method')->nullable();
            $table->boolean('is_scaned')->nullable();
            $table->timestamp('booking_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate_bookings');
    }
};
