<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'price',
        'yearly_price',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'features',
        'duration_days',
        'yearly_duration_days'
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'yearly_duration_days' => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
