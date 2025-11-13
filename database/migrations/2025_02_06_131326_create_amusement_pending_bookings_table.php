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
        Schema::create('amusement_pending_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id');
            $table->string('user_id');
            $table->text('session_id')->nullable();
            $table->string('promocode_id')->nullable();
            $table->string('attendee_id')->nullable();
            $table->string('token')->nullable();
            $table->decimal('amount', 30, 0)->default(0);
            $table->decimal('total_tax', 30, 0)->nullable()->default(0);
            $table->string('email')->nullable();
            $table->string('name', 30)->nullable();
            $table->string('number')->nullable();
            $table->string('type', 10)->nullable();
            $table->text('dates')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('discount', 30, 0)->nullable();
            $table->string('status')->nullable();
            $table->string('payment_status', 25)->nullable()->default(0);
            $table->string('log_status')->nullable();
            $table->string('device', 30)->nullable();
            $table->decimal('base_amount', 30, 0)->nullable()->default(0);
            $table->decimal('convenience_fee', 30, 0)->nullable();
            $table->string('txnid')->nullable();
            $table->string('easepayid')->nullable();
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
        Schema::dropIfExists('amusement_pending_bookings');

    }
};
