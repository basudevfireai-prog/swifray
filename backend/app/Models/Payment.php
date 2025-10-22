<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'transaction_id', 'amount', 'method', 'status'];

    // Inverse relationship: A Payment belongs to one Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
