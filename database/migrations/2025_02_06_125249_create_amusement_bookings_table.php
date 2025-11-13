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
        Schema::create('amusement_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id')->nullable();
            $table->string('user_id')->nullable();
            $table->text('session_id')->nullable();
            $table->bigInteger('attendee_id')->nullable();
            $table->string('txnid')->nullable();
            $table->string('agent_id')->nullable();
            $table->string('promocode_id')->nullable();
            $table->string('token')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('total_tax', 30, 0)->nullable()->default(0);
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('number')->nullable();
            $table->string('type')->nullable();
            $table->text('dates')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('device')->nullable();
            $table->decimal('base_amount', 10, 2)->nullable();
            $table->decimal('convenience_fee', 10, 2)->nullable();
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
        Schema::dropIfExists('amusement_bookings');
    }
};
