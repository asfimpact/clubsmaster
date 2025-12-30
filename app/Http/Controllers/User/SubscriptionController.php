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
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'frequency' => 'required|in:monthly,yearly',
        ]);

        $user = Auth::user();
        $plan = Plan::findOrFail($request->plan_id);

        // Calculate trial duration using Database as Source of Truth
        $validityDays = ($request->frequency === 'yearly')
            ? ($plan->yearly_duration_days ?? 365)
            : $plan->duration_days;

        // Cancel any existing subscriptions first
        $existingSubscription = $user->subscriptions()->where('type', 'default')->first();
        if ($existingSubscription) {
            $existingSubscription->delete();
        }

        // Create subscription directly for free plans (no Stripe API call)
        // We MUST set 'stripe_status' to 'active' for Cashier's subscribed() check to pass.
        // We use 'trial_ends_at' to handle the duration.
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'plan_id' => $plan->id,
            'stripe_id' => 'free_' . \Illuminate\Support\Str::random(10), // Dummy Stripe ID for consistency
            'stripe_status' => 'active', // CRITICAL: Makes $user->subscribed() return true
            'stripe_price' => 'free-plan', // Placeholder price ID
            'quantity' => 1,
            'trial_ends_at' => Carbon::now()->addDays($validityDays),
            'ends_at' => null, // Stays active until trial ends
        ]);

        // Return updated user data with subscription plan loaded
        return response()->json([
            'message' => 'Subscription activated successfully! You have ' . $validityDays . ' days of access.',
            'user' => $user->fresh()->load(['subscription.plan']),
            'subscription' => $subscription->fresh()->load('plan'),
        ]);
    }
}
