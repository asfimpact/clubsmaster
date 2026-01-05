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

    /**
     * Smart billing label accessor.
     * Automatically generates human-readable billing period labels.
     * 
     * @return string
     */
    public function getBillingLabelAttribute()
    {
        // Use the duration days to decide the label
        return match (intval($this->yearly_duration_days)) {
            7 => 'Per Week',
            14 => 'Per 2 Weeks',
            30 => 'Per Month',
            90 => 'Per Quarter',
            180 => 'Per 6 Months',
            365 => 'Per Year',
            default => 'Per Period',
        };
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
