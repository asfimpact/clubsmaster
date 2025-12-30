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

        // Calculate Expiry using Database as Source of Truth
        $validityDays = ($request->frequency === 'yearly')
            ? ($plan->yearly_duration_days ?? 365) // Fallback 365 if column is somehow null
            : $plan->duration_days;

        $expiresAt = Carbon::now()->addDays($validityDays);

        // Update or Create Subscription
        // Assuming one active subscription per user logic
        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id' => $plan->id,
                'start_date' => Carbon::now(),
                'end_date' => $expiresAt,
                'status' => 'active',
                // Add price/frequency logging here if you have columns for it in subscriptions table
                // For now, minimal schema assumption
            ]
        );

        // Return updated user data with subscription plan loaded
        return response()->json([
            'message' => 'Subscription updated successfully',
            'user' => $user->load(['subscription.plan'])
        ]);
    }
}
