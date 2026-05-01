<?php

namespace App\Events;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentAccessDeliveryReady
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array{type?:string, link?:string, email?:string, password?:string, product_type?:string}  $access
     */
    public function __construct(
        public User $user,
        public Product $product,
        public array $access = [],
    ) {}
}

