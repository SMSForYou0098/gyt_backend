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
        Schema::table('users', function (Blueprint $table) {
            $table->string('number')->after('email')->nullable();
            $table->string('organisation')->after('email')->nullable();
            $table->string('alt_number')->after('email')->nullable();

            // address
            $table->text('address')->after('email')->nullable();
            $table->string('pincode')->after('email')->nullable();
            $table->string('state')->after('email')->nullable();
            $table->string('city')->after('email')->nullable();

            //banking
            $table->string('bank_name')->after('email')->nullable();
            $table->string('bank_number')->after('email')->nullable();
            $table->string('bank_ifsc')->after('email')->nullable();
            $table->string('bank_branch')->after('email')->nullable();
            $table->string('bank_micr')->after('email')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
