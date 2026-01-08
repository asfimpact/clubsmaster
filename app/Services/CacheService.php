<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    // Cache key constants
    const INVOICES = 'invoices';
    const SUBSCRIPTION = 'subscription';
    const PLANS = 'plans_list';
    const PERMISSIONS = 'permissions';
    const MEMBERSHIP = 'membership_history';
    const SUMMARY = 'subscription_summary';
    const USER_DATA = 'user_data';

    // TTL constants (in seconds)
    const TTL_SHORT = 300;   // 5 minutes
    const TTL_MEDIUM = 600;  // 10 minutes
    const TTL_LONG = 3600;   // 1 hour

    /**
     * Generate cache key for user-specific data
     */
    private static function userKey(int $userId, string $type): string
    {
        return "user_{$userId}_{$type}";
    }

    /**
     * Clear all caches for a specific user
     */
    public static function clearUser(int $userId): void
    {
        try {
            // Clear all user-specific caches
            Cache::forget(self::userKey($userId, self::USER_DATA));
            Cache::forget(self::userKey($userId, self::SUBSCRIPTION));
            Cache::forget(self::userKey($userId, self::INVOICES));
            Cache::forget(self::userKey($userId, self::MEMBERSHIP));
            Cache::forget(self::userKey($userId, self::SUMMARY));
            Cache::forget(self::userKey($userId, self::PERMISSIONS));

            Log::info("Cache cleared for user", ['user_id' => $userId]);
        } catch (\Exception $e) {
            Log::error("Failed to clear cache for user", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Warm critical caches for a user
     */
    public static function warmUser(int $userId): void
    {
        try {
            $user = User::with('subscription.plan')->find($userId);

            if (!$user) {
                Log::warning("Cannot warm cache - user not found", ['user_id' => $userId]);
                return;
            }

            // Warm user data cache
            Cache::put(
                self::userKey($userId, self::USER_DATA),
                $user,
                self::TTL_SHORT
            );

            // Warm subscription cache
            if ($user->subscription) {
                Cache::put(
                    self::userKey($userId, self::SUBSCRIPTION),
                    $user->subscription,
                    self::TTL_SHORT
                );
            }

            // Warm subscription summary cache
            Cache::put(
                self::userKey($userId, self::SUMMARY),
                self::buildSubscriptionSummary($user),
                self::TTL_SHORT
            );

            Log::info("Cache warmed for user", ['user_id' => $userId]);
        } catch (\Exception $e) {
            Log::error("Failed to warm cache for user", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get user data with caching
     */
    public static function getUser(int $userId)
    {
        return Cache::remember(
            self::userKey($userId, self::USER_DATA),
            self::TTL_SHORT,
            function () use ($userId) {
                return User::with('subscription.plan')->find($userId);
            }
        );
    }

    /**
     * Get user subscription with caching
     */
    public static function getSubscription(int $userId)
    {
        return Cache::remember(
            self::userKey($userId, self::SUBSCRIPTION),
            self::TTL_SHORT,
            function () use ($userId) {
                $user = User::find($userId);
                return $user?->subscription()->with('plan')->first();
            }
        );
    }

    /**
     * Get user invoices with caching
     */
    public static function getInvoices(int $userId, bool $fresh = false)
    {
        $cacheKey = self::userKey($userId, self::INVOICES);

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            self::TTL_MEDIUM,
            function () use ($userId) {
                $user = User::find($userId);

                if (!$user || !$user->stripe_id) {
                    return [];
                }

                try {
                    return $user->invoices()->map(function ($invoice) {
                        return [
                            'id' => $invoice->id,
                            'date' => $invoice->date()->format('M d, Y'),
                            'amount' => $invoice->total(),
                            'status' => ucfirst($invoice->status),
                            'description' => $invoice->lines->data[0]->description ?? 'Subscription',
                            'pdf_url' => $invoice->hosted_invoice_url,
                        ];
                    });
                } catch (\Exception $e) {
                    Log::error('Failed to fetch invoices', [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                    return [];
                }
            }
        );
    }

    /**
     * Get membership history with caching
     */
    public static function getMembershipHistory(int $userId)
    {
        return Cache::remember(
            self::userKey($userId, self::MEMBERSHIP),
            self::TTL_MEDIUM,
            function () use ($userId) {
                $user = User::find($userId);
                return $user?->subscriptions()->with('plan')->orderBy('created_at', 'desc')->get();
            }
        );
    }

    /**
     * Get plans list with caching (global cache)
     */
    public static function getPlans()
    {
        return Cache::remember(
            self::PLANS,
            self::TTL_LONG,
            function () {
                return \App\Models\Plan::all();
            }
        );
    }

    /**
     * Clear plans cache (when admin updates plans)
     */
    public static function clearPlans(): void
    {
        Cache::forget(self::PLANS);
        Log::info("Plans cache cleared");
    }

    /**
     * Build subscription summary for user
     */
    private static function buildSubscriptionSummary(User $user): array
    {
        $subscription = $user->subscription;

        return [
            'plan_name' => $subscription?->plan?->name ?? 'No Active Plan',
            'status' => $subscription?->stripe_status ?? 'inactive',
            'expiry_date' => $subscription?->ends_at?->format('M d, Y') ?? 'N/A',
            'can_access' => $subscription?->valid() ?? false,
        ];
    }

    /**
     * Get subscription summary with caching
     */
    public static function getSubscriptionSummary(int $userId)
    {
        return Cache::remember(
            self::userKey($userId, self::SUMMARY),
            self::TTL_SHORT,
            function () use ($userId) {
                $user = User::with('subscription.plan')->find($userId);
                return self::buildSubscriptionSummary($user);
            }
        );
    }

    /**
     * Clear and warm user cache (atomic operation)
     */
    public static function refreshUser(int $userId): void
    {
        self::clearUser($userId);
        self::warmUser($userId);
    }

    /**
     * Sync subscription data from Stripe (3-layer fallback)
     * This is the "heal" mechanism when DB data is stale
     */
    public static function syncFromStripe(int $userId): ?User
    {
        try {
            $user = User::find($userId);

            if (!$user || !$user->stripe_id) {
                Log::warning('Cannot sync from Stripe - user has no Stripe ID', [
                    'user_id' => $userId
                ]);
                return $user;
            }

            Log::info('🔄 Syncing user data from Stripe (3-layer fallback)', [
                'user_id' => $userId,
                'stripe_id' => $user->stripe_id
            ]);

            // Fetch fresh data from Stripe
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Get Stripe customer
            $stripeCustomer = \Stripe\Customer::retrieve($user->stripe_id);

            // Get active subscription from Stripe
            $stripeSubscriptions = \Stripe\Subscription::all([
                'customer' => $user->stripe_id,
                'status' => 'active',
                'limit' => 1
            ]);

            if ($stripeSubscriptions->data && count($stripeSubscriptions->data) > 0) {
                $stripeSubscription = $stripeSubscriptions->data[0];

                // Find or create local subscription
                $localSubscription = \App\Models\Subscription::where('stripe_id', $stripeSubscription->id)->first();

                if ($localSubscription) {
                    // ✅ DB HEALING: Update local subscription with Stripe data
                    $periodEnd = $stripeSubscription->current_period_end
                        ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                        : null;

                    $oldStatus = $localSubscription->stripe_status;
                    $oldPeriodEnd = $localSubscription->current_period_end;

                    $localSubscription->update([
                        'stripe_status' => $stripeSubscription->status,
                        'current_period_end' => $periodEnd,
                        'ends_at' => $stripeSubscription->cancel_at_period_end ? $periodEnd : null,
                    ]);

                    Log::info('🏥 DB HEALED: Subscription updated from Stripe fallback', [
                        'user_id' => $userId,
                        'subscription_id' => $stripeSubscription->id,
                        'old_status' => $oldStatus,
                        'new_status' => $stripeSubscription->status,
                        'old_period_end' => $oldPeriodEnd?->toDateString(),
                        'new_period_end' => $periodEnd?->toDateString(),
                    ]);
                }
            }

            // Refresh user model
            $user->refresh()->load('subscription.plan');

            // Clear and warm cache with fresh data
            self::refreshUser($userId);

            Log::info('✅ User data synced from Stripe successfully', [
                'user_id' => $userId
            ]);

            return $user;

        } catch (\Exception $e) {
            Log::error('❌ Failed to sync from Stripe', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Get subscription with 3-layer fallback
     * Layer 1: Cache
     * Layer 2: Database
     * Layer 3: Stripe API (if DB is stale)
     */
    public static function getSubscriptionWithFallback(int $userId)
    {
        // Layer 1: Try cache
        $cacheKey = self::userKey($userId, self::SUBSCRIPTION);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        // Layer 2: Try database
        $user = User::find($userId);
        $subscription = $user?->subscription()->with('plan')->first();

        if ($subscription) {
            // Check if data is stale using configurable threshold
            $staleThreshold = config('cache.stale_thresholds.subscription', 3600);
            $isStale = $subscription->updated_at->lt(now()->subSeconds($staleThreshold));

            if ($isStale && $user->stripe_id) {
                Log::info('⚠️ Subscription data is stale, syncing from Stripe', [
                    'user_id' => $userId,
                    'last_updated' => $subscription->updated_at->toDateTimeString(),
                    'stale_threshold' => $staleThreshold . 's',
                ]);

                // Layer 3: Sync from Stripe
                $freshUser = self::syncFromStripe($userId);
                $subscription = $freshUser?->subscription;
            }

            // Cache the result
            if ($subscription) {
                Cache::put($cacheKey, $subscription, self::TTL_SHORT);
            }

            return $subscription;
        }

        // No subscription found
        return null;
    }

    /**
     * Detect and heal stale invoice data
     */
    public static function healStaleInvoices(int $userId): void
    {
        try {
            $user = User::find($userId);

            if (!$user || !$user->stripe_id) {
                return;
            }

            // Check if cached invoices are stale
            $cacheKey = self::userKey($userId, self::INVOICES);
            $cached = Cache::get($cacheKey);

            if ($cached === null || count($cached) === 0) {
                // Force refresh from Stripe
                Log::info('🔄 Healing stale invoices from Stripe', [
                    'user_id' => $userId
                ]);

                self::getInvoices($userId, true); // Force fresh fetch
            }
        } catch (\Exception $e) {
            Log::error('Failed to heal stale invoices', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
