<?php

namespace App\Observers;

use App\Models\User;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->clearAndWarmCache($user, 'updated');
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Just clear cache, no warming needed
        try {
            CacheService::clearUser($user->id);
            Log::info("User deleted - cache cleared", [
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to clear cache on user deletion", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear and warm cache for user changes
     */
    private function clearAndWarmCache(User $user, string $event): void
    {
        try {
            // Clear user cache
            CacheService::clearUser($user->id);

            // Warm cache immediately (no cold cache penalty)
            CacheService::warmUser($user->id);

            Log::info("User {$event} - cache refreshed", [
                'user_id' => $user->id,
                'event' => $event
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to refresh cache on user {$event}", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
