<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingAddress extends Model
{
    protected $fillable = [
        'user_id',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    /**
     * Get the user that owns the billing address.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
