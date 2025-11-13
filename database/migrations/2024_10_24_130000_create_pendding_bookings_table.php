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
        Schema::create('pendding_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id');
            $table->string('user_id');
            // $table->string('agent_id')->nullable();
            // $table->string('promocode_id')->nullable();
            $table->string('token');
            $table->decimal('amount', 30, 0)->default(0);
            $table->string('email')->nullable();
            $table->string('name', 30)->nullable();
            $table->string('number')->nullable();
            $table->string('type', 10)->nullable();
            $table->text('dates')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('discount', 30, 0)->nullable();
            $table->string('status');
            $table->string('device', 30)->nullable();
            $table->decimal('base_amount', 30, 0)->default(0);
            $table->decimal('convenience_fee', 30, 0)->nullable();
            $table->string('txnid')->nullable();
            $table->string('easepayid')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendding_bookings');
    }
};
