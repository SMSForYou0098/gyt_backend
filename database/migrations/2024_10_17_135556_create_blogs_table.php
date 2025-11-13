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
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Author of the blog (if tied to a user table)
            $table->string('title'); // Blog title
            $table->text('content'); // Blog content
            $table->string('slug')->unique(); // URL slug for SEO
            $table->json('tags'); // Author of the blog
            $table->json('categories')->nullable(); // Date the blog is published
            $table->string('meta_keyword')->nullable(); // Meta keywords for SEO
            $table->text('meta_description')->nullable(); // Meta description for SEO
            $table->string('meta_title')->nullable(); // Meta title for SEO
            $table->string('thumbnail')->nullable(); // Thumbnail image path or URL
            $table->timestamps(); // Created at and updated at timestamps
            $table->softDeletes(); // Soft delete column

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
