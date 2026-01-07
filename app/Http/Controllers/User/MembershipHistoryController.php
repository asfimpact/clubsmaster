<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MembershipHistoryController extends Controller
{
    /**
     * Get user's membership history (plan changes over time).
     * Shows timeline of subscriptions with plan details.
     * 
     * IMPORTANT: Uses eager loading ->with('plan') to prevent N+1 queries.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Use App\Models\Subscription directly (not Cashier's Subscription)
        // Cashier's Subscription model doesn't have 'plan' relationship
        // Eager load 'plan' to prevent N+1 query performance issues
        $memberships = \App\Models\Subscription::where('user_id', $user->id)
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        $mapped = $memberships->map(function ($sub) {
            // Determine frequency from stripe_price comparison
            $frequency = 'Monthly'; // Default
            if ($sub->plan && $sub->stripe_price === $sub->plan->stripe_yearly_price_id) {
                $frequency = 'Yearly';
            }

            // Determine the expiry/renewal date
            $endsAt = null;

            // Priority 1: For active Stripe subscriptions, use current_period_end (renewal date)
            if ($sub->stripe_status === 'active' && $sub->current_period_end) {
                $endsAt = $sub->current_period_end->format('M d, Y');
            }
            // Priority 2: For trialing Stripe subscriptions, use trial_ends_at
            elseif ($sub->stripe_status === 'trialing' && $sub->trial_ends_at) {
                $endsAt = $sub->trial_ends_at->format('M d, Y');
            }
            // Priority 3: For local/free trials, use ends_at
            elseif ($sub->ends_at) {
                $endsAt = $sub->ends_at->format('M d, Y');
            }

            return [
                'id' => $sub->id,
                'plan_name' => ($sub->plan->name ?? 'Unknown Plan') . " ({$frequency})",
                'status' => $sub->stripe_status,
                'status_color' => match ($sub->stripe_status) {
                    'active' => 'success',
                    'trialing' => 'info',
                    'free' => 'info',
                    'cancelled' => 'warning',
                    default => 'error',
                },
                'status_text' => match ($sub->stripe_status) {
                    'active' => 'Active',
                    'trialing' => 'Trial',
                    'free' => 'Free',
                    'cancelled' => 'Cancelled',
                    default => ucfirst($sub->stripe_status),
                },
                'started_at' => $sub->created_at->format('M d, Y'),
                'ends_at' => $endsAt, // Renewal date for active, expiry for others, null if none
            ];
        });

        return response()->json(['memberships' => $mapped]);
    }
}
