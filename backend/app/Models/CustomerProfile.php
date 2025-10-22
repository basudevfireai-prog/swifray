<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    protected $fillable = ['user_id', 'default_address', 'ai_chat_settings'];

    // One-to-One inverse relationship: A CustomerProfile belongs to one User
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
