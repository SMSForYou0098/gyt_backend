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
        Schema::create('highlight_events', function (Blueprint $table) {
            $table->id();
            $table->string('sr_no')->nullable();
            $table->text('category')->nullable();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->longText('sub_description')->nullable();
            $table->string('button_link')->nullable();
            $table->string('button_text')->nullable();
            $table->boolean('external_url')->nullable();
            $table->text('images')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('highlight_events');
    }
};
