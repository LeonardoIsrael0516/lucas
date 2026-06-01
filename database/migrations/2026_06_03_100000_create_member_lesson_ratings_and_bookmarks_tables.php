<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            $table->unsignedInteger('ratings_count')->default(0)->after('likes_count');
            $table->unsignedInteger('ratings_sum')->default(0)->after('ratings_count');
        });

        Schema::create('member_lesson_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_lesson_id')->constrained('member_lessons')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();
            $table->unique(['user_id', 'member_lesson_id']);
            $table->index('member_lesson_id');
        });

        Schema::create('member_lesson_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_lesson_id')->constrained('member_lessons')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'member_lesson_id']);
            $table->index('member_lesson_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_lesson_bookmarks');
        Schema::dropIfExists('member_lesson_ratings');

        Schema::table('member_lessons', function (Blueprint $table) {
            $table->dropColumn(['ratings_count', 'ratings_sum']);
        });
    }
};
