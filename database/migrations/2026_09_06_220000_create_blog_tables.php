<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title_hu');
            $table->string('title_en')->nullable();
            $table->string('excerpt_hu', 500)->nullable();
            $table->string('excerpt_en', 500)->nullable();
            $table->longText('body_hu');
            $table->longText('body_en')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('blog_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blog_post_id', 'user_id']);
        });

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_likes');
        Schema::dropIfExists('blog_posts');
    }
};
