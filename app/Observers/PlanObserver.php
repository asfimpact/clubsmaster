<?php

namespace App\Observers;

use App\Models\Plan;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class PlanObserver
{
    /**
     * Handle the Plan "created" event.
     */
    public function created(Plan $plan): void
    {
        $this->clearPlansCache('created', $plan->id);
    }

    /**
     * Handle the Plan "updated" event.
     */
    public function updated(Plan $plan): void
    {
        $this->clearPlansCache('updated', $plan->id);
    }

    /**
     * Handle the Plan "deleted" event.
     */
    public function deleted(Plan $plan): void
    {
        $this->clearPlansCache('deleted', $plan->id);
    }

    /**
     * Clear plans cache when admin makes changes
     */
    private function clearPlansCache(string $event, int $planId): void
    {
        try {
            // Clear global plans cache
            CacheService::clearPlans();

            Log::info("Plan {$event} - plans cache cleared", [
                'plan_id' => $planId,
                'event' => $event
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to clear plans cache on plan {$event}", [
                'plan_id' => $planId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
