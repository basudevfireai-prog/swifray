<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id',
        'license_number',
        'insurance_details',
        'vehicle_type',
        'license_doc_url',
        'insurance_doc_url',
        'document_status',
        'is_available',
    ];

    // One-to-One inverse relationship: A DriverProfile belongs to one User
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
