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
        Schema::create('seat_configs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->nullable();
            $table->string('ground_type')->nullable();
            $table->json('config')->nullable();
            $table->string('event_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_configs');
    }
};
