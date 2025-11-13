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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('footer_logo')->nullable();
            $table->text('footer_address')->nullable();
            $table->string('footer_contact')->nullable();
            $table->string('nav_logo')->nullable();
            $table->text('site_credit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('footer_logo');
            $table->dropColumn('footer_address');
            $table->dropColumn('footer_contact');
            $table->dropColumn('nav_logo');
            $table->dropColumn('site_credit');
        });
    }
};
