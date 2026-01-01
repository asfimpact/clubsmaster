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
                // EXISTING SUBSCRIBER: Check if they have a payment method in Stripe
                $hasPaymentMethod = false;

                try {
                    // Check Stripe directly, not just local DB
                    if ($user->stripe_id) {
                        $paymentMethods = $user->paymentMethods();
                        $hasPaymentMethod = $paymentMethods->isNotEmpty();

                        Log::info('Payment method check', [
                            'user_id' => $user->id,
                            'has_payment_method' => $hasPaymentMethod,
                            'count' => $paymentMethods->count(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not check payment methods from Stripe', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // If no payment method in Stripe, redirect to checkout
                if (!$hasPaymentMethod) {
                    Log::warning('Swap blocked - no payment method in Stripe, creating checkout session', [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'frequency' => $request->frequency,
                    ]);

                    // Create checkout session directly (same as new subscriber flow)
                    $checkout = $user->newSubscription('default', $stripePriceId)
                        ->withMetadata([
                            'plan_id' => $plan->id,
                            'user_id' => $user->id,
                            'frequency' => $request->frequency,
                        ])
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
                        'fallback' => true, // Indicate this was a fallback
                    ]);
                }

                // Payment method exists - proceed with swap
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

                // Ensure user has a Stripe customer ID
                if (!$user->stripe_id) {
                    try {
                        $user->createOrGetStripeCustomer();
                        $user->refresh();

                        Log::info('Stripe customer created', [
                            'user_id' => $user->id,
                            'stripe_id' => $user->stripe_id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to create Stripe customer', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);

                        return response()->json([
                            'error' => 'Failed to create Stripe customer. Please try again.',
                        ], 500);
                    }
                }

                $checkout = $user->newSubscription('default', $stripePriceId)
                    ->withMetadata([
                        'plan_id' => $plan->id,
                        'user_id' => $user->id,
                        'frequency' => $request->frequency,
                    ])
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

    /**
     * List all payment methods for the authenticated user
     */
    public function listPaymentMethods(Request $request)
    {
        $user = $request->user();

        $paymentMethods = $user->paymentMethods()->map(function ($pm) use ($user) {
            return [
                'id' => $pm->id,
                'brand' => $pm->card->brand,
                'last4' => $pm->card->last4,
                'exp_month' => $pm->card->exp_month,
                'exp_year' => $pm->card->exp_year,
                'is_default' => $pm->id === $user->defaultPaymentMethod()?->id,
            ];
        });

        return response()->json(['payment_methods' => $paymentMethods]);
    }

    /**
     * Create a SetupIntent for adding a new payment method
     */
    public function createSetupIntent(Request $request)
    {
        $user = $request->user();

        try {
            $intent = $user->createSetupIntent();

            return response()->json([
                'client_secret' => $intent->client_secret,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create SetupIntent', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create payment setup. Please try again.',
            ], 500);
        }
    }

    /**
     * Set a payment method as default
     */
    public function setDefaultPaymentMethod(Request $request, $pmId)
    {
        $user = $request->user();

        try {
            $user->updateDefaultPaymentMethod($pmId);

            // Also sync the pm_last_four to the users table immediately
            $pm = $user->findPaymentMethod($pmId);

            if ($pm && isset($pm->card)) {
                $user->update([
                    'pm_type' => $pm->card->brand,
                    'pm_last_four' => $pm->card->last4,
                ]);
            }

            Log::info('Default payment method updated', [
                'user_id' => $user->id,
                'pm_id' => $pmId,
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to set default payment method', [
                'user_id' => $user->id,
                'pm_id' => $pmId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to set default payment method.',
            ], 500);
        }
    }

    /**
     * Delete a payment method
     */
    public function deletePaymentMethod(Request $request, $pmId)
    {
        $user = $request->user();

        // Check if user has active subscription
        $activeSubCount = \App\Models\Subscription::where('user_id', $user->id)
            ->where('stripe_status', 'active')
            ->count();

        $paymentMethodsCount = $user->paymentMethods()->count();

        // DEBUG: Trace this
        Log::info("Delete Payment Method Attempt", [
            'user_id' => $user->id,
            'active_subs_count' => $activeSubCount,
            'payment_methods_count' => $paymentMethodsCount,
            'pm_id' => $pmId
        ]);

        if ($activeSubCount > 0) {
            if ($paymentMethodsCount <= 1) {
                Log::warning("Delete blocked - last payment method", [
                    'user_id' => $user->id,
                    'pm_id' => $pmId
                ]);

                return response()->json([
                    'error' => 'Cannot delete your only payment method while subscription is active.',
                ], 400);
            }
        }

        try {
            $user->deletePaymentMethod($pmId);

            Log::info('Payment method deleted', [
                'user_id' => $user->id,
                'pm_id' => $pmId,
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to delete payment method', [
                'user_id' => $user->id,
                'pm_id' => $pmId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete payment method.',
            ], 500);
        }
    }

    /**
     * Get or update billing address
     */
    public function getBillingAddress(Request $request)
    {
        $user = $request->user();
        $address = $user->billingAddress;

        return response()->json(['address' => $address]);
    }

    /**
     * Update billing address
     */
    public function updateBillingAddress(Request $request)
    {
        $validated = $request->validate([
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|size:2',
        ]);

        $user = $request->user();

        try {
            // Save to local DB
            $address = $user->billingAddress()->updateOrCreate(
                ['user_id' => $user->id],
                $validated
            );

            // Sync to Stripe
            if ($user->stripe_id) {
                $user->updateStripeCustomer([
                    'address' => [
                        'line1' => $validated['line1'],
                        'line2' => $validated['line2'],
                        'city' => $validated['city'],
                        'state' => $validated['state'],
                        'postal_code' => $validated['postal_code'],
                        'country' => $validated['country'],
                    ],
                ]);

                Log::info('Billing address synced to Stripe', ['user_id' => $user->id]);
            }

            return response()->json(['address' => $address]);
        } catch (\Exception $e) {
            Log::error('Failed to update billing address', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update billing address.',
            ], 500);
        }
    }
}
