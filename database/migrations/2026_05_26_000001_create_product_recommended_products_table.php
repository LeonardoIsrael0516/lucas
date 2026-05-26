<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_recommended_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->uuid('recommended_product_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'recommended_product_id'], 'prod_rec_prod_pair_unique');
            $table->foreign('product_id', 'prod_rec_prod_product_fk')
                ->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('recommended_product_id', 'prod_rec_prod_recommended_fk')
                ->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recommended_products');
    }
};
