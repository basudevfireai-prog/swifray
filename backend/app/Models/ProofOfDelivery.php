<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProofOfDelivery extends Model
{
    protected $fillable = ['order_id', 'type', 'photo_url', 'signature_url', 'notes'];

    // Inverse relationship: A Proof belongs to one Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
