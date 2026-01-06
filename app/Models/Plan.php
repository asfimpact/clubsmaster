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
        'yearly_duration_days',
        'is_enabled'
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'yearly_duration_days' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected $appends = [
        'billing_label',
        'period_info',
    ];

    /**
     * Smart billing label accessor.
     * Automatically generates human-readable billing period labels.
     * Supports standard periods (week, month, quarter, etc.) and custom day counts.
     * 
     * @return string
     */
    public function getBillingLabelAttribute()
    {
        $days = intval($this->yearly_duration_days);

        return match (true) {
            $days === 7 => 'Per Week',
            $days === 30 => 'Per Month',
            $days === 90 => 'Per Quarter',
            $days === 180 => 'Per 6 Months',
            $days === 365 => 'Per Year',
            $days < 30 => "Per {$days} Days",  // Short custom periods
            default => "Per {$days} Days",  // Any other custom period
        };
    }

    /**
     * Scope to filter only enabled plans.
     * Used to hide disabled plans from user-facing endpoints.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Get structured period information (Value Object).
     * Returns all billing-related metadata in one object.
     * This prevents hardcoded 'monthly'/'yearly' checks throughout the codebase.
     * 
     * @return array
     */
    public function getPeriodInfoAttribute(): array
    {
        $days = $this->yearly_duration_days ?? 365;

        // Determine interval type for Stripe/API compatibility
        $interval = match (true) {
            $days == 7 => 'week',
            $days == 30 => 'month',
            $days == 365 => 'year',
            $days % 30 == 0 => 'month',  // Multi-month intervals
            default => 'day',
        };

        // Calculate interval count (e.g., 6 for "6 months")
        $intervalCount = match ($interval) {
            'week' => (int) ($days / 7),
            'month' => (int) ($days / 30),
            'year' => (int) ($days / 365),
            'day' => $days,
            default => 1,
        };

        // Determine if this is a standard or custom period
        $standardPeriods = [7, 30, 90, 180, 365];
        $isStandard = in_array($days, $standardPeriods);

        return [
            'label' => $this->billing_label,           // "Per Month", "Per Quarter", etc.
            'days' => $days,                            // 30, 90, 180, 365, etc.
            'interval' => $interval,                    // 'day', 'week', 'month', 'year'
            'interval_count' => $intervalCount,         // 1, 3, 6, 12, etc.
            'is_standard' => $isStandard,               // true for standard periods
            'is_monthly' => $days == 30,                // Convenience flag
            'is_yearly' => $days == 365,                // Convenience flag
            'is_custom' => !$isStandard,                // true for non-standard periods
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
