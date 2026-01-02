<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'current_period_end',
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
