<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BillingController extends Controller
{
    /**
     * Get user billing and subscription status.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $subscription = $user->subscription()->with('plan')->first();

        // Default State (No Subscription)
        $data = [
            'plan_name' => 'Not Active',
            'plan_price' => 0,
            'status' => 'inactive',
            'active_until' => 'N/A',
            'days_consumed' => 0,
            'total_days' => 0,
            'days_remaining' => 0,
            'progress_percent' => 0,
            'currency' => '£', // Default currency
        ];

        if ($subscription && $subscription->status === 'active' && $subscription->plan) {
            $startDate = Carbon::parse($subscription->start_date);
            $endDate = Carbon::parse($subscription->end_date);
            $now = Carbon::now();

            $totalDays = $startDate->diffInDays($endDate);
            // Avoiding division by zero for weird 0-day plans
            $totalDays = $totalDays > 0 ? $totalDays : 1;

            // Calculate consumption
            // If now is before start (future?), consumed is 0. 
            // If now is after end (expired), consumed is max.
            if ($now->lt($startDate)) {
                $daysConsumed = 0;
            } elseif ($now->gt($endDate)) {
                $daysConsumed = $totalDays;
            } else {
                $daysConsumed = $startDate->diffInDays($now);
            }

            $daysRemaining = $now->diffInDays($endDate, false); // false = allows negative if expired

            // Cap logic
            if ($daysRemaining < 0)
                $daysRemaining = 0;
            if ($daysConsumed > $totalDays)
                $daysConsumed = $totalDays;

            $progress = ($daysConsumed / $totalDays) * 100;

            $data = [
                'plan_name' => $subscription->plan->name,
                'plan_price' => $subscription->plan->price,
                'status' => 'active',
                'active_until' => $endDate->format('M d, Y'),
                'days_consumed' => (int) $daysConsumed,
                'total_days' => (int) $totalDays,
                'days_remaining' => (int) $daysRemaining,
                'progress_percent' => round($progress),
                'currency' => '£', // Assuming GBP for now from Plan model logic
            ];
        }

        return response()->json($data);
    }
}
