<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('phone_pes', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('client_id');          // From dashboard "Client Id"
            $table->string('client_secret');      // From dashboard "Client Secret"
            $table->string('client_version');     // From dashboard "Client Version"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_pes');
    }
};
