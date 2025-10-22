<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'driver_id',
        'delivery_type',
        'parcel_details',
        'total_amount',
        'status'
    ];

    // --- Relationships ---

    // An Order belongs to a Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // An Order belongs to a Driver (can be null)
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    // An Order has many Locations (pickup and dropoff)
    public function locations()
    {
        return $this->hasMany(OrderLocation::class);
    }

    // An Order has one Payment record
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    // An Order has many Tracking history records
    public function tracking()
    {
        return $this->hasMany(OrderTracking::class);
    }

    // An Order has many Proof of Deliveries (pickup and dropoff)
    public function proofOfDeliveries()
    {
        return $this->hasMany(ProofOfDelivery::class);
    }

    // An Order has one Earning record for the driver
    public function earning()
    {
        return $this->hasOne(DriverEarning::class);
    }
}
