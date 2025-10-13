<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = ['rating_avg','verification_status','payout_account_id','user_id'];

    public function vehicles() {
        return $this->belongsToMany(Vehicle::class, 'driver_vehicle', 'driver_id', 'vehicle_id');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
