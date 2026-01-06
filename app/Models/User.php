<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, \Laravel\Sanctum\HasApiTokens, \Laravel\Cashier\Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'two_factor_verified_at',
        'two_factor_code',
        'two_factor_expires_at',
        'email_verified_at',
        'last_activity_at',
        'pm_type',           // Cashier: Payment method type
        'pm_last_four',      // Cashier: Payment method last 4 digits
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['computed_status', 'current_plan', 'current_subscription_frequency', 'subscription_summary'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_verified_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's computed status based on security flow.
     */
    public function getComputedStatusAttribute()
    {
        if ($this->status === 'suspended') {
            return 'Suspended';
        }

        if (!$this->email_verified_at) {
            return 'Pending';
        }

        // Check global 2FA setting
        $is2faEnabled = DB::table('settings')->where('key', '2fa_enabled')->value('value') === '1';

        if ($is2faEnabled && !$this->two_factor_verified_at) {
            return 'Inactive';
        }

        return 'Active';
    }

    /**
     * Get the current plan details if the user has a subscription.
     */
    public function getCurrentPlanAttribute()
    {
        // Use already-loaded relationship if available
        if ($this->relationLoaded('subscription')) {
            $subscription = $this->getRelation('subscription');
        } else {
            $subscription = $this->subscription()->with('plan')->first();
        }

        return $subscription ? $subscription->plan : null;
    }

    /**
     * Get the current subscription frequency (monthly or yearly).
     */
    public function getCurrentSubscriptionFrequencyAttribute()
    {
        // Use already-loaded relationship if available
        if ($this->relationLoaded('subscription')) {
            $subscription = $this->getRelation('subscription');
            if ($subscription && !$subscription->relationLoaded('plan')) {
                $subscription->load('plan');
            }
        } else {
            $subscription = $this->subscription()->with('plan')->first();
        }

        if (!$subscription || !$subscription->plan) {
            return null;
        }

        // Compare stripe_price with plan's price IDs to determine frequency
        if ($subscription->stripe_price === $subscription->plan->stripe_yearly_price_id) {
            return 'yearly';
        }
        if ($subscription->stripe_price === $subscription->plan->stripe_monthly_price_id) {
            return 'monthly';
        }

        // Default to monthly if we can't determine
        return 'monthly';
    }


    /**
     * Get the user's subscription (for eager loading).
     * Always returns the LATEST active subscription to prevent "zombie" subscriptions.
     * Supports: active paid plans, free plans, and Stripe trials.
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('type', 'default')
            ->whereIn('stripe_status', ['active', 'free', 'trialing'])
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->with('plan') // Eager-load plan to avoid N+1 queries
            ->latestOfMany();
    }

    /**
     * Check if user has an active subscription.
     * Uses Cashier's native subscribed() method.
     */
    public function hasActiveSubscription()
    {
        return $this->subscribed('default');
    }

    /**
     * Get normalized subscription summary for UI display.
     * Returns consistent data structure regardless of subscription state.
     */
    public function getSubscriptionSummaryAttribute()
    {
        $subscription = $this->subscription()->first();

        // No active subscription
        if (!$subscription) {
            return [
                'status' => 'Inactive',
                'plan_name' => 'No Active Plan',
                'expiry_date' => 'N/A',
                'price' => 'Free',
                'currency' => 'GBP',
                'billing_cycle' => null,
                'days_remaining' => 0,
            ];
        }

        $plan = $subscription->plan;
        $status = 'Active';
        $expiryDate = 'N/A';
        $price = 'Free';
        $billingCycle = null;

        // Determine status and expiry based on stripe_status
        if ($subscription->stripe_status === 'free') {
            // Free plan
            $status = 'Active (Free)';
            $expiryDate = $subscription->ends_at
                ? $subscription->ends_at->format('M d, Y')
                : 'N/A';
            $price = 'Free';
        } elseif ($subscription->stripe_status === 'trialing') {
            // Stripe trial - card collected, will charge after trial
            $status = 'Active (Trial)';
            $expiryDate = $subscription->trial_ends_at
                ? $subscription->trial_ends_at->format('M d, Y')
                : 'N/A';

            // Show the price that will be charged after trial
            if ($plan && $subscription->stripe_price === $plan->stripe_yearly_price_id) {
                $price = '£' . $plan->yearly_price . ' (after trial)';
                $billingCycle = $plan->billing_label;
            } elseif ($plan && $subscription->stripe_price === $plan->stripe_monthly_price_id) {
                $price = '£' . $plan->price . ' (after trial)';
                $billingCycle = 'monthly';
            } else {
                $price = $plan ? '£' . $plan->price . ' (after trial)' : 'N/A';
                $billingCycle = 'monthly';
            }
        } elseif ($subscription->onGracePeriod()) {
            // Canceled but still in grace period (Cashier Native Method)
            // CHECK THIS BEFORE 'active' because Stripe keeps status='active' during grace period
            $status = 'Active (Cancelling)';
            $expiryDate = $subscription->ends_at ? $subscription->ends_at->format('M d, Y') : 'N/A';

            // Keep showing price during grace period
            // Compare against actual price IDs instead of string matching
            if ($plan && $subscription->stripe_price === $plan->stripe_yearly_price_id) {
                $price = '£' . $plan->yearly_price;
                $billingCycle = $plan->billing_label; // Smart label from Plan model
            } elseif ($plan && $subscription->stripe_price === $plan->stripe_monthly_price_id) {
                $price = '£' . $plan->price;
                $billingCycle = 'monthly';
            } else {
                // Fallback if price ID doesn't match (shouldn't happen)
                $price = $plan ? '£' . $plan->price : 'N/A';
                $billingCycle = 'monthly';
            }
        } elseif ($subscription->stripe_status === 'active') {
            // Active paid subscription (Recurring)
            $status = 'Active';

            // Waterfall of Truth: Check current_period_end, then trial_ends_at, then ends_at
            $expiryTimestamp = $subscription->current_period_end
                ?? $subscription->trial_ends_at
                ?? $subscription->ends_at;

            $expiryDate = $expiryTimestamp
                ? $expiryTimestamp->format('M d, Y')
                : 'Syncing...';

            // Determine price and billing cycle from plan
            // FIXED: Compare against actual price IDs instead of string matching
            // Uses Plan's smart billing_label accessor for dynamic labels
            if ($plan && $subscription->stripe_price === $plan->stripe_yearly_price_id) {
                $price = '£' . $plan->yearly_price;
                $billingCycle = $plan->billing_label; // Smart label: Per Week, Per 6 Months, etc.
            } elseif ($plan && $subscription->stripe_price === $plan->stripe_monthly_price_id) {
                $price = '£' . $plan->price;
                $billingCycle = 'monthly';
            } else {
                // Fallback if price ID doesn't match (shouldn't happen)
                $price = $plan ? '£' . $plan->price : 'N/A';
                $billingCycle = 'monthly';
            }
        } else {
            // Expired or other status
            $status = 'Inactive';
            $expiryDate = 'Expired';
            $price = 'Free';
        }

        // Calculate days remaining (STRICT: as integer)
        $daysRemaining = 0;
        if ($subscription->stripe_status === 'free' && $subscription->ends_at) {
            // Free plan: calculate from ends_at
            $daysRemaining = (int) max(0, now()->diffInDays($subscription->ends_at, false));
        } elseif ($subscription->stripe_status === 'trialing' && $subscription->trial_ends_at) {
            // Trial: calculate from trial_ends_at
            $daysRemaining = (int) max(0, now()->diffInDays($subscription->trial_ends_at, false));
        } elseif ($subscription->stripe_status === 'active' && $subscription->current_period_end) {
            // Paid plan: calculate from current_period_end
            $daysRemaining = (int) max(0, now()->diffInDays($subscription->current_period_end, false));
        } elseif ($subscription->stripe_status === 'canceled' && $subscription->ends_at && $subscription->ends_at->isFuture()) {
            // Canceled but in grace period: calculate from ends_at
            $daysRemaining = (int) max(0, now()->diffInDays($subscription->ends_at, false));
        }

        return [
            'status' => $status,
            'plan_name' => $plan ? $plan->name : 'Unknown Plan',
            'expiry_date' => $expiryDate,
            'price' => $price,
            'currency' => 'GBP',
            'billing_cycle' => $billingCycle,
            'days_remaining' => $daysRemaining,
        ];
    }

    /**
     * Get the user's billing address.
     */
    public function billingAddress()
    {
        return $this->hasOne(\App\Models\BillingAddress::class);
    }
}
