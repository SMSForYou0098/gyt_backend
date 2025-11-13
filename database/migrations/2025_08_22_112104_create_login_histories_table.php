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
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->longText('location')->nullable();
            $table->longText('country')->nullable();
            $table->longText('state')->nullable();
            $table->longText('city')->nullable();
            $table->timestamp('login_time')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
