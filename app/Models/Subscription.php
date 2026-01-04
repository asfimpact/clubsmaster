<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'type',
        'plan_id',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'current_period_end',
        'starts_at', // Added for subscription start date tracking
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'starts_at' => 'datetime',
            'current_period_end' => 'datetime',
        ]);
    }

    /**
     * Relationship to the Plan model.
     * Maintains our custom plan_id field.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
