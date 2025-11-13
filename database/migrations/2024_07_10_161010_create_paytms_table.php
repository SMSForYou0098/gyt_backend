<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('paytms', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('merchant_key')->nullable();
            $table->string('merchant_website')->nullable();
            $table->string('industry_type')->nullable();
            $table->string('channel')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paytms');
    }
};
