<?php

namespace App\Services;

use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Subscribe user to a plan
     */
    public function subscribe(User $user, Plan $plan, string $frequency = 'monthly'): array
    {
        return DB::transaction(function () use ($user, $plan, $frequency) {
            try {
                // Business logic here (Stripe checkout, local subscription, etc.)

                // After subscription created, clear and warm cache
                CacheService::refreshUser($user->id);

                Log::info('Subscription created', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'frequency' => $frequency
                ]);

                return [
                    'success' => true,
                    'message' => 'Subscription created successfully',
                    'user' => CacheService::getUser($user->id),
                ];
            } catch (\Exception $e) {
                Log::error('Subscription creation failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        });
    }

    /**
     * Cancel user subscription
     */
    public function cancel(User $user): array
    {
        return DB::transaction(function () use ($user) {
            try {
                $subscription = $user->subscription()->first();

                if (!$subscription) {
                    return [
                        'success' => false,
                        'message' => 'No active subscription found'
                    ];
                }

                // Cancel subscription logic
                if ($subscription->stripe_id) {
                    $subscription->cancel();
                } else {
                    $subscription->update([
                        'stripe_status' => 'canceled',
                        'ends_at' => now()
                    ]);
                }

                // Clear and warm cache
                CacheService::refreshUser($user->id);

                Log::info('Subscription canceled', ['user_id' => $user->id]);

                return [
                    'success' => true,
                    'message' => 'Subscription canceled successfully',
                    'user' => CacheService::getUser($user->id),
                ];
            } catch (\Exception $e) {
                Log::error('Subscription cancellation failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        });
    }

    /**
     * Resume canceled subscription
     */
    public function resume(User $user): array
    {
        return DB::transaction(function () use ($user) {
            try {
                $subscription = $user->subscription()->first();

                if (!$subscription || !$subscription->onGracePeriod()) {
                    return [
                        'success' => false,
                        'message' => 'No canceled subscription to resume'
                    ];
                }

                // Resume subscription logic
                if ($subscription->stripe_id) {
                    $subscription->resume();
                } else {
                    $subscription->update([
                        'stripe_status' => 'active',
                        'ends_at' => null
                    ]);
                }

                // Clear and warm cache
                CacheService::refreshUser($user->id);

                Log::info('Subscription resumed', ['user_id' => $user->id]);

                return [
                    'success' => true,
                    'message' => 'Subscription resumed successfully',
                    'user' => CacheService::getUser($user->id),
                ];
            } catch (\Exception $e) {
                Log::error('Subscription resume failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        });
    }

    /**
     * Sync subscription from Stripe
     */
    public function syncFromStripe(User $user): void
    {
        try {
            if (!$user->stripe_id) {
                return;
            }

            // Sync logic here (fetch from Stripe, update DB)

            // Clear and warm cache after sync
            CacheService::refreshUser($user->id);

            Log::info('Subscription synced from Stripe', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('Stripe sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
