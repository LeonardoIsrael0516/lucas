<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            if (! Schema::hasColumn('member_lessons', 'attachment_files')) {
                $table->json('attachment_files')->nullable()->after('content_files');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            if (Schema::hasColumn('member_lessons', 'attachment_files')) {
                $table->dropColumn('attachment_files');
            }
        });
    }
};
