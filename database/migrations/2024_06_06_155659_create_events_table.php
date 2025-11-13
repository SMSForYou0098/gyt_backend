<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('category')->nullable();
            $table->string('name')->nullable();

            $table->text('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();

            $table->string('short_info')->nullable();
            $table->text('description')->nullable();
            $table->text('offline_payment_instruction')->nullable();
            // $table->string('customer_care_number')->nullable();
            // toggles
            $table->string('event_feature')->nullable();
            $table->string('status')->nullable();
            $table->string('house_full')->nullable();
            $table->string('sms_otp_checkout')->nullable();

            // timing
            $table->date('date_range')->nullable();
            $table->time('start_time')->nullable();
            $table->time('entry_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('repeatitive')->nullable();
            $table->string('repeatitive_type')->nullable();
            $table->string('repeatitive_value')->nullable();

            //location
            $table->string('map_code')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('image_1')->nullable();
            $table->string('image_2')->nullable();
            $table->string('image_3')->nullable();
            $table->string('image_4')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('multi_qr')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
