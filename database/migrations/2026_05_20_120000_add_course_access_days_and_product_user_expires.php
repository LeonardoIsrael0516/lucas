<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'course_access_days')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('course_access_days')->nullable()->after('member_area_cover');
            });
        }

        if (Schema::hasTable('product_user') && ! Schema::hasColumn('product_user', 'access_expires_at')) {
            Schema::table('product_user', function (Blueprint $table) {
                $table->timestamp('access_expires_at')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'course_access_days')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('course_access_days');
            });
        }

        if (Schema::hasTable('product_user') && Schema::hasColumn('product_user', 'access_expires_at')) {
            Schema::table('product_user', function (Blueprint $table) {
                $table->dropColumn('access_expires_at');
            });
        }
    }
};
