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
        Schema::create('l_zones', function (Blueprint $table) {
            $table->id();
            $table->string('venue_id')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->boolean('is_blocked')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('l_zones');
    }
};
