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
        Schema::create('welcome_pop_ups', function (Blueprint $table) {
            $table->id();
            $table->longText('image')->nullable();
            $table->longText('sm_image')->nullable();
            $table->string('url')->nullable();
            $table->string('sm_url')->nullable();
            $table->text('text')->nullable();
            $table->text('sm_text')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('welcome_pop_ups');
    }
};
