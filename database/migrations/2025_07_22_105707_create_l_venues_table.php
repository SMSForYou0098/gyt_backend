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
        Schema::create('l_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->longText('location')->nullable();
            $table->enum('venue_type', ['stadium', 'auditorium', 'theater'])->nullable();
            $table->integer('capacity')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('l_venues');
    }
};
