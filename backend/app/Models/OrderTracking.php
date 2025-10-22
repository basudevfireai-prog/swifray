<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTracking extends Model
{
    protected $fillable = ['order_id', 'status_code', 'status_message'];

    // Inverse relationship: A Tracking entry belongs to one Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
