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
        Schema::create('l_rows', function (Blueprint $table) {
            $table->id();
            $table->string('section_id')->nullable();
            $table->string('label')->nullable();
            $table->integer('seats')->nullable();
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
        Schema::dropIfExists('l_rows');
    }
};
