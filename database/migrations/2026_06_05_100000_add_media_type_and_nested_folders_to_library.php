<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_pdf_library_items', function (Blueprint $table) {
            $table->string('media_type', 32)->default('pdf')->after('mime')->index();
            $table->unique(['tenant_id', 'storage_path'], 'member_pdf_library_items_tenant_path_unique');
        });

        Schema::table('member_pdf_library_folders', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->foreignId('parent_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('member_pdf_library_folders')
                ->nullOnDelete();
            $table->unique(['tenant_id', 'parent_id', 'name'], 'member_pdf_library_folders_tenant_parent_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('member_pdf_library_folders', function (Blueprint $table) {
            $table->dropUnique('member_pdf_library_folders_tenant_parent_name_unique');
            $table->dropConstrainedForeignId('parent_id');
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('member_pdf_library_items', function (Blueprint $table) {
            $table->dropUnique('member_pdf_library_items_tenant_path_unique');
            $table->dropColumn('media_type');
        });
    }
};
