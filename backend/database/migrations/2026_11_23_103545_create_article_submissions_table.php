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
        Schema::create('article_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('subtitle', 300)->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('cover_image_url', 2048)->nullable();
            $table->string('cover_image_credit', 500)->nullable();
            $table->string('category_slug', 120)->nullable();
            $table->json('tags')->nullable();
            $table->string('format', 32)->default('DISPATCH');
            $table->string('status', 32)->default('DRAFT')->index();
            $table->text('feedback')->nullable();
            $table->string('published_slug')->nullable();
            $table->unsignedBigInteger('wordpress_post_id')->nullable()->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['author_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_submissions');
    }
};
