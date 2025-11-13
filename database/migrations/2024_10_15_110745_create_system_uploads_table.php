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
        Schema::create('system_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['video', 'image']);
            $table->string('url');
            $table->boolean('photo_required')->default(1);
            $table->boolean('attendy_required')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_uploads');
    }
};
