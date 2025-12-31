<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    /**
     * Create a Stripe Checkout Session for subscription payment.
     */
    public function checkout(Request $request)
    {
        // Debug: Log incoming request data
        Log::info('Stripe Checkout Request', [
            'all_data' => $request->all(),
            'plan_id' => $request->input('plan_id'),
            'frequency' => $request->input('frequency'),
            'user_id' => $request->user()?->id,
        ]);

        $validator = \Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'frequency' => 'required|in:monthly,yearly',
        ]);

        if ($validator->fails()) {
            Log::error('Stripe Checkout Validation Failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all(),
            ]);
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $plan = Plan::findOrFail($request->plan_id);

        // Guard: Prevent free plans from using Stripe Checkout
        // Free plans should use the /api/user/subscribe endpoint
        if ($plan->price <= 0 && $request->frequency === 'monthly') {
            return response()->json(['error' => 'Free plans should be activated directly, not via Stripe.'], 400);
        }
        if (($plan->yearly_price <= 0 || $plan->price <= 0) && $request->frequency === 'yearly') {
            // Assuming if monthly is free, yearly is likely free or special 0 price logic
            // But strict check on the price being charged is better
            $priceToCheck = $request->frequency === 'yearly' ? $plan->yearly_price : $plan->price;
            if ($priceToCheck <= 0) {
                return response()->json(['error' => 'Free plans should be activated directly, not via Stripe.'], 400);
            }
        }

        // Determine the correct Stripe Price ID based on frequency
        $stripePriceId = $request->frequency === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        // Validate that the plan has a Stripe price ID configured
        if (empty($stripePriceId)) {
            return response()->json([
                'error' => 'This plan does not have a Stripe price configured for ' . $request->frequency . ' billing.'
            ], 400);
        }

        try {
            // Check if user already has an active subscription
            // Use direct query to avoid conflict with custom subscription() relationship
            $existingSubscription = \App\Models\Subscription::where('user_id', $user->id)
                ->where('type', 'default')
                ->where('stripe_status', 'active')
                ->first();

            if ($existingSubscription) {
                // EXISTING SUBSCRIBER: Use swap to change plan/frequency
                Log::info('Existing subscriber detected - using swap', [
                    'user_id' => $user->id,
                    'current_price' => $existingSubscription->stripe_price,
                    'new_price' => $stripePriceId,
                ]);

                $subscription = $existingSubscription;

                // Swap to the new price (Cashier handles pro-rating automatically)
                $subscription->swap($stripePriceId);

                // Update our custom plan_id field (Cashier doesn't know about this)
                $subscription->update([
                    'plan_id' => $plan->id,
                ]);

                Log::info('Subscription swapped successfully', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'stripe_price' => $stripePriceId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Your plan has been updated successfully!',
                    'swapped' => true,
                ]);
            } else {
                // NEW SUBSCRIBER: Create Stripe Checkout Session
                Log::info('New subscriber - creating checkout session', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ]);

                $checkout = $user->newSubscription('default', $stripePriceId)
                    ->checkout([
                        'success_url' => url('/?payment=success'),
                        'cancel_url' => url('/pages/pricing?payment=cancelled'),
                        'metadata' => [
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'frequency' => $request->frequency,
                        ],
                    ]);

                return response()->json([
                    'url' => $checkout->url,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Stripe Checkout Error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'plan_id' => $request->plan_id,
                'frequency' => $request->frequency,
            ]);

            return response()->json([
                'error' => 'Failed to create checkout session. Please try again later.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
