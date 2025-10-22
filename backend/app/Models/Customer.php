<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name','type','billing_account_id','user_id'];


    // --- Relationships ---

    // One-to-Many relationship with Customer
    public function user() {
        return $this->belongsTo(User::class);
    }

    // One-to-One relationship with CustomerProfile
    public function customerProfile()
    {
        return $this->hasOne(CustomerProfile::class);
    }

    // One-to-One relationship with DriverProfile
    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    // A User (Customer) can place many Orders
    public function placedOrders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

}
