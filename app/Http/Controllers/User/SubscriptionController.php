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
}
