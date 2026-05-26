<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            if (! Schema::hasColumn('member_lessons', 'resource_links')) {
                $table->json('resource_links')->nullable()->after('content_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            if (Schema::hasColumn('member_lessons', 'resource_links')) {
                $table->dropColumn('resource_links');
            }
        });
    }
};
