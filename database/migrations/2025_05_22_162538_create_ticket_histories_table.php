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
        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('batch_id')->nullable();
            $table->string('name')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('ticket_quantity')->nullable();
            $table->integer('booking_per_customer')->nullable();
            $table->text('description')->nullable();
            $table->string('taxes')->nullable();
            $table->boolean('sale')->nullable();
            $table->datetime('sale_date')->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->boolean('sold_out')->nullable();
            $table->boolean('booking_not_open')->nullable();
            $table->string('ticket_template')->nullable();
            $table->boolean('fast_filling')->nullable();
            $table->string('background_image')->nullable();
            $table->json('promocode_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_histories');
    }
};
