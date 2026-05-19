<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_modules', function (Blueprint $table) {
            $table->dropForeign(['member_section_id']);
        });

        Schema::table('member_modules', function (Blueprint $table) {
            $table->unsignedBigInteger('member_section_id')->nullable()->change();
            $table->string('cover_mode', 32)->default('vertical')->after('thumbnail');
        });

        Schema::table('member_modules', function (Blueprint $table) {
            $table->foreign('member_section_id')
                ->references('id')
                ->on('member_sections')
                ->nullOnDelete();
        });

        $productIds = DB::table('products')
            ->where('type', 'area_membros')
            ->pluck('id');

        foreach ($productIds as $productId) {
            $position = 0;
            $sections = DB::table('member_sections')
                ->where('product_id', $productId)
                ->orderBy('position')
                ->get();

            foreach ($sections as $section) {
                $coverMode = $section->cover_mode ?? 'vertical';
                $modules = DB::table('member_modules')
                    ->where('member_section_id', $section->id)
                    ->orderBy('position')
                    ->get();

                foreach ($modules as $module) {
                    DB::table('member_modules')
                        ->where('id', $module->id)
                        ->update([
                            'position' => ++$position,
                            'member_section_id' => null,
                            'cover_mode' => $coverMode,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('member_modules', function (Blueprint $table) {
            $table->dropForeign(['member_section_id']);
        });

        Schema::table('member_modules', function (Blueprint $table) {
            $table->dropColumn('cover_mode');
        });

        Schema::table('member_modules', function (Blueprint $table) {
            $table->unsignedBigInteger('member_section_id')->nullable(false)->change();
            $table->foreign('member_section_id')
                ->references('id')
                ->on('member_sections')
                ->cascadeOnDelete();
        });
    }
};
