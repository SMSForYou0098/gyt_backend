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
        Schema::create('intramojo_configs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->unique();
            $table->string('api_key');
            $table->string('auth_token');
            $table->enum('env', ['test', 'prod'])->default('test');
            $table->string('test_url')->nullable();
            $table->string('prod_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intramojo_configs');
    }
};
