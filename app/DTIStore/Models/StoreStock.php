<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StoreStock extends Model
{
    protected $fillable = [
        'quantity',
        'store_id',
        'current_delivery_quantity',
        'product_variation_id',
        'deleted_at'
    ];
}
