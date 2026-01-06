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
    protected $appends = ['computed_status', 'current_plan', 'current_subscription_frequency', 'subscription_summary', 'access_control'];

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
     * Get access control information for frontend/mobile apps.
     * Self-healing: Frontend doesn't calculate, just reads boolean flags.
     * Android-ready: Clear can_access flag with reason.
     * 
     * @return array
     */
    public function getAccessControlAttribute(): array
    {
        $subscription = $this->subscription;

        // No subscription
        if (!$subscription) {
            return [
                'can_access' => false,
                'reason' => 'no_subscription',
                'status' => 'inactive',
                'expires_at' => null,
                'expires_at_unix' => null,
                'days_remaining' => 0,
                'is_trial' => false,
                'is_free' => false,
                'is_paid' => false,
                'is_cancelling' => false,
            ];
        }

        // Determine access based on subscription status
        $canAccess = in_array($subscription->stripe_status, ['active', 'free', 'trialing']);

        // Check if subscription is expired
        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            $canAccess = false;
        }

        // Determine reason
        $reason = match ($subscription->stripe_status) {
            'active' => $subscription->onGracePeriod() ? 'cancelling' : 'active',
            'free' => 'free_plan',
            'trialing' => 'trial_active',
            'canceled' => 'expired',
            default => 'unknown',
        };

        // Calculate expiry timestamp
        $expiryDate = match ($subscription->stripe_status) {
            'free' => $subscription->ends_at,
            'trialing' => $subscription->trial_ends_at,
            'active' => $subscription->onGracePeriod()
            ? $subscription->ends_at
            : $subscription->current_period_end,
            default => $subscription->ends_at,
        };

        // Calculate days remaining
        $daysRemaining = 0;
        if ($expiryDate && $expiryDate->isFuture()) {
            $daysRemaining = (int) max(0, now()->diffInDays($expiryDate, false));
        }

        return [
            // Primary access flag (Android uses this)
            'can_access' => $canAccess,

            // Reason for access status
            'reason' => $reason,

            // Human-readable status
            'status' => $canAccess ? 'active' : 'inactive',

            // Expiry information
            'expires_at' => $expiryDate?->toIso8601String(),
            'expires_at_unix' => $expiryDate?->timestamp,
            'days_remaining' => $daysRemaining,

            // Subscription type flags
            'is_trial' => $subscription->stripe_status === 'trialing',
            'is_free' => $subscription->stripe_status === 'free',
            'is_paid' => $subscription->stripe_status === 'active' && !$subscription->onGracePeriod(),
            'is_cancelling' => $subscription->onGracePeriod(),
        ];
    }

    /**
     * Get normalized subscription summary for UI display.
     * HYBRID EXPERT MODEL: Supports dual price IDs (monthly + yearly) while using Value Objects.
     * Returns consistent data structure regardless of subscription state.
     * 
     * @return array
     */
    public function getSubscriptionSummaryAttribute(): array
    {
        $subscription = $this->subscription;

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

        // Hybrid Expert Logic: Detect if user is on yearly or monthly billing
        // by comparing stripe_price with plan's price IDs
        $isYearly = $plan && $subscription->stripe_price === $plan->stripe_yearly_price_id;

        // Use the appropriate price and billing label based on detection
        // For yearly: use yearly_price and billing_label (e.g., "Per Year", "Per 6 Months")
        // For monthly: always use "Per Month" (toggle is simple: monthly vs yearly)
        $basePrice = $isYearly ? ($plan?->yearly_price ?? 0) : ($plan?->price ?? 0);
        $billingCycle = $isYearly
            ? ($plan?->billing_label ?? 'Per Year')
            : 'Per Month';  // Hardcoded for monthly toggle

        // Determine status and expiry based on stripe_status
        $status = 'Active';
        $expiryDate = 'N/A';
        $price = 'Free';
        $daysRemaining = 0;

        if ($subscription->stripe_status === 'free') {
            // Free plan
            $status = 'Active (Free)';
            $expiryDate = $subscription->ends_at?->format('M d, Y') ?? 'N/A';
            $price = 'Free';

            if ($subscription->ends_at) {
                $daysRemaining = (int) max(0, now()->diffInDays($subscription->ends_at, false));
            }

        } elseif ($subscription->stripe_status === 'trialing') {
            // Stripe trial - card collected, will charge after trial
            $status = 'Active (Trial)';
            $expiryDate = $subscription->trial_ends_at?->format('M d, Y') ?? 'N/A';
            $price = '£' . number_format($basePrice, 2) . ' (after trial)';

            if ($subscription->trial_ends_at) {
                $daysRemaining = (int) max(0, now()->diffInDays($subscription->trial_ends_at, false));
            }

        } elseif ($subscription->onGracePeriod()) {
            // Canceled but still in grace period
            $status = 'Active (Cancelling)';
            $expiryDate = $subscription->ends_at?->format('M d, Y') ?? 'N/A';
            $price = '£' . number_format($basePrice, 2);

            if ($subscription->ends_at && $subscription->ends_at->isFuture()) {
                $daysRemaining = (int) max(0, now()->diffInDays($subscription->ends_at, false));
            }

        } elseif ($subscription->stripe_status === 'active') {
            // Active paid subscription
            $status = 'Active';

            $expiryTimestamp = $subscription->current_period_end
                ?? $subscription->trial_ends_at
                ?? $subscription->ends_at;

            $expiryDate = $expiryTimestamp?->format('M d, Y') ?? 'Syncing...';
            $price = '£' . number_format($basePrice, 2);

            if ($subscription->current_period_end) {
                $daysRemaining = (int) max(0, now()->diffInDays($subscription->current_period_end, false));
            }

        } else {
            // Expired or other status
            $status = 'Inactive';
            $expiryDate = 'Expired';
            $price = 'Free';
        }

        // Calculate total days for progress bar
        // For free plans, use duration_days; for paid, use 30 (monthly default)
        $totalDays = 30; // Default monthly
        if ($subscription->stripe_status === 'free' && $plan) {
            $totalDays = $plan->duration_days ?? 30;
        }

        return [
            'status' => $status,
            'plan_name' => $plan?->name ?? 'Unknown Plan',
            'expiry_date' => $expiryDate,
            'price' => $price,
            'currency' => 'GBP',
            'billing_cycle' => $billingCycle,
            'days_remaining' => $daysRemaining,
            'total_days' => $totalDays,  // For progress bar
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
