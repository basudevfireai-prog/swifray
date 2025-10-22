<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverEarning extends Model
{
    protected $fillable = ['driver_id', 'order_id', 'amount', 'payment_date', 'status'];

    // Inverse relationship: An Earning belongs to one Driver
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    // Inverse relationship: An Earning is tied to one Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
