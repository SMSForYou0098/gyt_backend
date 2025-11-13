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
        Schema::create('cashfree_configs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // Organizer or Admin ID
            $table->string('app_id'); // Cashfree App ID
            $table->string('secret_key'); // Cashfree Secret Key
            $table->enum('env', ['test', 'live'])->default('test'); // Environment (test/live)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashfree_configs');
    }
};
