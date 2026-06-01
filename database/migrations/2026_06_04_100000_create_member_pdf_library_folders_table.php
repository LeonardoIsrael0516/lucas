<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_pdf_library_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('member_pdf_library_items', function (Blueprint $table) {
            $table->foreignId('folder_id')
                ->nullable()
                ->after('product_id')
                ->constrained('member_pdf_library_folders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('member_pdf_library_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('member_pdf_library_folders');
    }
};
