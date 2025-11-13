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
        Schema::create('catrgoty_has__fields', function (Blueprint $table) {
            $table->id();
            $table->integer('category_id')->nullable(); // Foreign key to categories table
            $table->json('custom_fields_id')->nullable(); // Foreign key to categories table

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catrgoty_has__fields');
    }
};
