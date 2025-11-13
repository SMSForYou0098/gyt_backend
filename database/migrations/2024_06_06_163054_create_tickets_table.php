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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->string('event_id')->nullable();
            $table->string('name')->nullable();

            $table->string('currency')->nullable();
            $table->string('ticket_quantity')->nullable();
            $table->string('booking_per_customer')->nullable();
            // $table->text('description')->nullable();

            $table->string('taxes')->nullable();
            $table->string('sale_label')->nullable();
            $table->time('sale_date')->nullable();
            // toggles
            $table->string('ticket_terms')->nullable();
            $table->string('backgorund_image')->nullable();
            $table->string('sold_out')->nullable();
            $table->string('donation')->nullable();



            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
