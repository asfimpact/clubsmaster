<?php

namespace App\Observers;

use App\Models\Subscription;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class SubscriptionObserver
{
    /**
     * Handle the Subscription "created" event.
     */
    public function created(Subscription $subscription): void
    {
        $this->clearAndWarmCache($subscription, 'created');
    }

    /**
     * Handle the Subscription "updated" event.
     */
    public function updated(Subscription $subscription): void
    {
        $this->clearAndWarmCache($subscription, 'updated');
    }

    /**
     * Handle the Subscription "deleted" event.
     */
    public function deleted(Subscription $subscription): void
    {
        $this->clearAndWarmCache($subscription, 'deleted');
    }

    /**
     * Clear and warm cache for subscription changes
     */
    private function clearAndWarmCache(Subscription $subscription, string $event): void
    {
        try {
            // Clear user cache
            CacheService::clearUser($subscription->user_id);

            // Warm cache immediately (no cold cache penalty)
            CacheService::warmUser($subscription->user_id);

            Log::info("Subscription {$event} - cache refreshed", [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'event' => $event
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to refresh cache on subscription {$event}", [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
