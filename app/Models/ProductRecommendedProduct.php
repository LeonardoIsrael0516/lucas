<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecommendedProduct extends Model
{
    protected $fillable = ['product_id', 'recommended_product_id', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recommendedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'recommended_product_id');
    }
}
