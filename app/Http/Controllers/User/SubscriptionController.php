<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Subscribe user to a plan.
     * FREE plans: Create local subscription (no Stripe)
     * PAID plans: Redirect to Stripe Checkout
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'frequency' => 'required|in:monthly,yearly',
        ]);

        $user = Auth::user();
        $plan = Plan::findOrFail($request->plan_id);

        // Determine the price based on frequency
        $price = ($request->frequency === 'yearly')
            ? ($plan->yearly_price ?? $plan->price)
            : $plan->price;

        // Check if plan is free (price = 0)
        if ($price == 0) {
            // Create local subscription (no Stripe)
            return $this->createFreeSubscription($user, $plan, $request->frequency);
        } else {
            // Redirect to Stripe Checkout for paid plans
            return response()->json([
                'redirect_to_stripe' => true,
                'message' => 'Redirecting to Stripe Checkout...',
            ]);
        }
    }

    /**
     * Create a free (local) subscription without Stripe
     */
    private function createFreeSubscription($user, $plan, $frequency)
    {
        // Prevent duplicate free subscriptions - ONE FREE TRIAL PER LIFETIME
        // Check entire subscription history (not just active ones)
        $everHadFree = $user->subscriptions()
            ->where('stripe_status', 'free')
            ->exists();

        if ($everHadFree) {
            return response()->json([
                'message' => 'You have already used your free trial. Please upgrade to a paid plan to continue.',
            ], 403); // 403 Forbidden - user is not allowed to do this
        }

        // Calculate validity duration using Database as Source of Truth
        $validityDays = ($frequency === 'yearly')
            ? ($plan->yearly_duration_days ?? 365)
            : $plan->duration_days;

        // Cancel any existing subscriptions first (gracefully expire, don't delete)
        $existingSubscription = $user->subscriptions()->where('type', 'default')->first();
        if ($existingSubscription) {
            $existingSubscription->update([
                'ends_at' => now(),
                'stripe_status' => 'cancelled'
            ]);
        }

        // Create FREE subscription (NO Stripe data)
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'plan_id' => $plan->id,
            'stripe_id' => null,              // IMPORTANT: No fake Stripe ID
            'stripe_status' => 'free',        // NOT 'active' - indicates local subscription
            'stripe_price' => null,           // No Stripe price
            'quantity' => 1,
            'trial_ends_at' => null,          // Reserved for Stripe trials only
            'ends_at' => Carbon::now()->addDays($validityDays), // Free plan expiry
        ]);

        // Return updated user data with subscription plan loaded
        return response()->json([
            'message' => 'Free subscription activated successfully! You have ' . $validityDays . ' days of access.',
            'user' => $user->fresh()->load(['subscription.plan']),
            'subscription' => $subscription->fresh()->load('plan'),
        ]);
    }

    /**
     * Cancel subscription (at period end)
     */
    public function cancel(Request $request)
    {
        $user = $request->user();
        Log::info("🛑 Cancel Request: User {$user->id} initiated cancellation.");

        try {
            // FETCH MODEL, NOT RELATION
            $subscription = $user->subscription()->first();

            if ($subscription) {
                Log::info("🔍 Subscription Found: ID {$subscription->id}, Stripe ID: " . ($subscription->stripe_id ?? 'NULL') . ", Status: {$subscription->stripe_status}");
            } else {
                Log::warning("⚠️ No 'default' subscription object found for User {$user->id}");
            }

            if ($subscription && $subscription->valid()) {
                if ($subscription->stripe_id) {
                    // Stripe: Cancel at period end
                    Log::info("🔄 Calling Stripe cancel() for sub: {$subscription->stripe_id}");
                    $subscription->cancel();
                } else {
                    // Local/Free: Mark as cancelled immediately or set ends_at
                    Log::info("ℹ️ Local Cancellation (No Stripe ID). Updating DB.");
                    $subscription->update([
                        'stripe_status' => 'canceled',
                        'ends_at' => now()
                    ]);
                }

                Log::info("✅ Cancellation Success for User {$user->id}");

                // Format expiry date for user-friendly message
                $expiryDate = $subscription->ends_at
                    ? $subscription->ends_at->format('M d, Y')
                    : 'the end of your billing period';

                return response()->json([
                    'message' => "Subscription cancelled. Access remains until {$expiryDate}.",
                    'user' => $user->fresh()->load('subscription.plan')
                ]);
            }

            Log::warning("⚠️ Cancel failed: valid() check returned false or sub null.");
            return response()->json(['message' => 'No active subscription to cancel.'], 404);
        } catch (\Exception $e) {
            Log::error("❌ Cancel Exception for User {$user->id}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Cancellation failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Resume cancelled subscription
     */
    public function resume(Request $request)
    {
        $user = $request->user();
        Log::info("▶️ Resume Request: User {$user->id}");

        try {
            // FETCH MODEL, NOT RELATION
            $subscription = $user->subscription()->first();

            if ($subscription && $subscription->onGracePeriod()) {
                if ($subscription->stripe_id) {
                    Log::info("🔄 Calling Stripe resume() for sub: {$subscription->stripe_id}");
                    $subscription->resume();
                } else {
                    // Local: Reactivate
                    Log::info("ℹ️ Local Resume.");
                    $subscription->update([
                        'stripe_status' => 'free',
                        'ends_at' => null
                    ]);
                }

                Log::info("✅ Resume Success for User {$user->id}");
                return response()->json([
                    'message' => 'Subscription resumed successfully.',
                    'user' => $user->fresh()->load('subscription.plan')
                ]);
            }

            Log::warning("⚠️ Resume failed: Not on grace period or sub null.");
            return response()->json(['message' => 'No subscription found to resume.'], 404);
        } catch (\Exception $e) {
            Log::error("❌ Resume Exception for User {$user->id}: " . $e->getMessage());
            return response()->json(['message' => 'Resume failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verify and sync subscription from Stripe if webhook failed/delayed
     * This is the "Safety Net" for payment success scenarios
     */
    public function verify(Request $request)
    {
        $user = $request->user();
        Log::info("🔍 Verify Request: User {$user->id}");

        try {
            // 1. SMART FAST PATH: Check if subscription is COMPLETE, not just exists
            $localSub = $user->subscription()->first();
            $isComplete = $localSub &&
                $localSub->starts_at &&
                $localSub->current_period_end &&
                $localSub->plan_id;

            if ($isComplete && $localSub->valid()) {
                Log::info("✅ Verify: Subscription is complete and synced for User {$user->id}");
                return response()->json([
                    'status' => 'synced',
                    'subscription' => $localSub->load('plan')
                ]);
            }

            // Log what's missing if incomplete
            if ($localSub && !$isComplete) {
                Log::warning("⚠️ Verify: Incomplete subscription data for User {$user->id}", [
                    'has_starts_at' => (bool) $localSub->starts_at,
                    'has_current_period_end' => (bool) $localSub->current_period_end,
                    'has_plan_id' => (bool) $localSub->plan_id,
                ]);
            }

            // 2. SAFETY NET: Check Stripe directly if no local subscription
            if (!$user->stripe_id) {
                Log::warning("⚠️ Verify: User {$user->id} has no Stripe ID");
                return response()->json(['status' => 'no_stripe_customer'], 404);
            }

            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $stripeSubscriptions = \Stripe\Subscription::all([
                'customer' => $user->stripe_id,
                'limit' => 1,
                'status' => 'active', // Only fetch active subs
            ]);

            if (empty($stripeSubscriptions->data)) {
                Log::info("ℹ️ Verify: No active Stripe subscription found for User {$user->id}");
                return response()->json(['status' => 'pending']);
            }

            // 3. MANUAL SYNC: Stripe has subscription but DB doesn't
            $stripeSub = $stripeSubscriptions->data[0];
            Log::info("🔄 Verify: Triggering manual sync for User {$user->id}, Stripe Sub: {$stripeSub->id}");

            // REUSE WEBHOOK LOGIC: Call the same sync method used in webhooks
            $this->syncSubscriptionFromStripe($user, $stripeSub);

            // Return fresh data
            $freshSub = $user->subscription()->first();
            return response()->json([
                'status' => 'synced',
                'subscription' => $freshSub->load('plan'),
                'source' => 'manual_verify' // Flag for analytics
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Verify Failed for User {$user->id}: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Verification failed. Please refresh the page.'
            ], 500);
        }
    }

    /**
     * Shared sync logic (used by both webhooks and manual verify)
     * Extracted to avoid code duplication
     */
    private function syncSubscriptionFromStripe($user, $stripeSubscription)
    {
        $subArray = $stripeSubscription->toArray();
        $periodEnd = $subArray['current_period_end'] ?? null;
        $planId = $subArray['metadata']['plan_id'] ?? null;

        // Get interval info for fallback
        $planObj = $stripeSubscription['items']['data'][0]['plan'] ?? [];
        $unit = $planObj['interval'] ?? 'month';
        $count = $planObj['interval_count'] ?? 1;

        // Calculate date (same logic as webhooks)
        if ($periodEnd) {
            $calculatedDate = Carbon::createFromTimestamp($periodEnd);
            $source = 'Stripe';
        } else {
            $calculatedDate = match ($unit) {
                'year' => now()->addYears($count),
                'month' => now()->addMonths($count),
                'week' => now()->addWeeks($count),
                'day' => now()->addDays($count),
                default => now()->addMonths($count),
            };
            $source = "Calculated ({$count} {$unit})";
        }

        // STEP 1: Check if subscription already exists
        $existing = Subscription::where('stripe_id', $stripeSubscription->id)->first();

        // STEP 2: Prepare data with smart field handling
        $updateData = [
            'user_id' => $user->id,
            'type' => 'default',

            // DYNAMIC FIELDS (always update - these change over time)
            'stripe_status' => 'active',
            'current_period_end' => $calculatedDate,
            'quantity' => 1,

            // PERMANENT FIELDS (fill if missing/empty, preserve if exists)
            // Using ?: (Elvis) instead of ?? to catch empty strings and '0000-00-00' dates
            'starts_at' => $existing->starts_at ?: (
                $subArray['start_date']
                ? Carbon::createFromTimestamp($subArray['start_date'])
                : now()
            ),
            'plan_id' => $existing->plan_id ?: $planId,
            'stripe_price' => $existing->stripe_price ?: ($subArray['items']['data'][0]['price']['id'] ?? null),
        ];

        // STEP 3: Log what we're doing (repair vs create)
        if (!$existing) {
            Log::info("📅 Creating new subscription with starts_at: " . $updateData['starts_at']->toDateString());
        } else {
            $repairLog = [
                'starts_at' => $existing->starts_at ? 'preserved' : 'filled',
                'plan_id' => $existing->plan_id ? 'preserved' : 'filled',
                'stripe_price' => $existing->stripe_price ? 'preserved' : 'filled',
            ];
            Log::info("🔧 Repairing subscription for User {$user->id}: " . json_encode($repairLog));

            // DEBUG: Log exact values before save
            Log::info("🔍 DEBUG: About to save", [
                'existing_starts_at' => $existing->starts_at,
                'existing_starts_at_type' => gettype($existing->starts_at),
                'update_data_starts_at' => $updateData['starts_at']->toDateString(),
                'subscription_id' => $existing->id,
            ]);
        }

        // STEP 4: Create or update subscription (idempotent)
        $subscription = Subscription::updateOrCreate(
            ['stripe_id' => $stripeSubscription->id], // Match by Stripe ID
            $updateData
        );

        // DEBUG: Verify save was successful
        Log::info("🔍 DEBUG: After save", [
            'saved_starts_at' => $subscription->starts_at ? $subscription->starts_at->toDateString() : 'NULL',
            'subscription_id' => $subscription->id,
        ]);

        Log::info("✅ Manual Sync Complete: Sub {$subscription->id}, Date: {$calculatedDate->toDateString()}, Source: {$source}");

        return $subscription;
    }
}
