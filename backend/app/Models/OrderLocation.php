<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLocation extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'latitude',
        'longitude',
        'address_line',
        'contact_person',
        'contact_phone',
    ];

    // Inverse relationship: A Location belongs to one Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
