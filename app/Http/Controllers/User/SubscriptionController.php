<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
}
