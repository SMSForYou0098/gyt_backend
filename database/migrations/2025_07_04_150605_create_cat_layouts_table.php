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
        Schema::create('cat_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('category_id')->nullable();
            $table->json('qr_code')->nullable();
            $table->json('user_photo')->nullable();
            $table->json('text_1')->nullable();
            $table->json('text_2')->nullable();
            $table->json('text_3')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cat_layouts');
    }
};
