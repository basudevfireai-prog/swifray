<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['type', 'capacity_liters', 'license_plate', 'driver_id'];

    public function vehicles() {
        return $this->belongsToMany(Driver::class, 'driver_vehicle', 'vehicle_id', 'driver_id');
    }
}
