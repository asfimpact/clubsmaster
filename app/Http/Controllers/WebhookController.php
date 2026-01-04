<?php

namespace App\Http\Controllers;

use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class WebhookController extends CashierController
{
    /**
     * Handle a Stripe subscription created event.
     * We override this to sync our custom plan_id from metadata.
     */
    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        $startTime = microtime(true);
        Log::info('🕐 Webhook START: customer.subscription.created', ['time' => $startTime]);

        // 1. Let Cashier do its default work first (creates the subscription in DB)
        $response = parent::handleCustomerSubscriptionCreated($payload);

        try {
            $data = $payload['data']['object'];
            $stripeId = $data['id'];

            // AGGRESSIVE SYNC (The Cheat Code): Link Plan ID & Date immediately
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $stripeSubscription = \Stripe\Subscription::retrieve($stripeId);

            $periodEnd = $stripeSubscription['current_period_end'] ?? null;

            // TRUTH: Check the actual plan interval AND count
            $planObj = $stripeSubscription['items']['data'][0]['plan'] ?? [];
            $unit = $planObj['interval'] ?? 'month'; // 'month', 'year', 'day', 'week'
            $count = $planObj['interval_count'] ?? 1;

            $planId = $stripeSubscription['metadata']['plan_id'] ?? null;

            $sub = Subscription::where('stripe_id', $stripeId)->first();

            if ($sub) {
                // Waterfall: Stripe Date -> Calculated Date based on REAL interval & count
                if ($periodEnd) {
                    $calculatedDate = \Carbon\Carbon::createFromTimestamp($periodEnd);
                    $source = 'Stripe';
                } else {
                    // ROBUST CALCULATION (Match Expression)
                    $calculatedDate = match ($unit) {
                        'year' => now()->addYears($count),
                        'month' => now()->addMonths($count),
                        'week' => now()->addWeeks($count),
                        'day' => now()->addDays($count),
                        default => now()->addMonths($count),
                    };
                    $source = "Calculated ({$count} {$unit})";
                }

                $updateData = [
                    'current_period_end' => $calculatedDate,
                    'stripe_status' => 'active',
                    'starts_at' => $stripeSubscription['start_date']
                        ? \Carbon\Carbon::createFromTimestamp($stripeSubscription['start_date'])
                        : now()
                ];
                if ($planId) {
                    $updateData['plan_id'] = $planId;
                }

                $sub->forceFill($updateData)->save();

                Log::info("🏁 RACE PROOF SYNC: Date saved for {$stripeId}", [
                    'period_end' => $calculatedDate->toDateString(),
                    'source' => $source
                ]);

                if (!$periodEnd) {
                    Log::info("🎯 FIXED SYNC (Created): Added {$count} {$unit}(s). New date: " . $calculatedDate->toDateString());
                }
            } else {
                Log::warning("Webhook Warning: Subscription {$stripeId} still not found after parent call");
            }
        } catch (\Exception $e) {
            Log::error('❌ Race Sync Failed: ' . $e->getMessage(), [
                'stripe_subscription_id' => $stripeId ?? 'unknown'
            ]);
        }

        $endTime = microtime(true);
        Log::info('✅ Webhook END: customer.subscription.created', [
            'time' => $endTime,
            'duration' => round(($endTime - $startTime) * 1000, 2) . 'ms'
        ]);

        return $response;
    }

    /**
     * Handle subscription updates to keep plan_id in sync if changed via Stripe Dashboard
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $startTime = microtime(true);
        Log::info('🕐 Webhook START: customer.subscription.updated', ['time' => $startTime]);

        // 1. Let Cashier update the subscription status/dates first
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        try {
            $data = $payload['data']['object'];
            $stripeId = $data['id'];

            // 2. FORCE RE-SYNC: Direct API call to get the new end date
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $stripeSubscription = \Stripe\Subscription::retrieve($stripeId);

            $periodEnd = $stripeSubscription['current_period_end'] ?? null;

            // TRUTH: Check the actual plan interval AND count
            $planObj = $stripeSubscription['items']['data'][0]['plan'] ?? [];
            $unit = $planObj['interval'] ?? 'month';
            $count = $planObj['interval_count'] ?? 1;

            $planId = $stripeSubscription['metadata']['plan_id'] ?? null;

            $sub = Subscription::where('stripe_id', $stripeId)->first();

            if ($sub) {
                if ($periodEnd) {
                    // HAPPY PATH: Stripe has the new date
                    $updateData = [
                        'current_period_end' => \Carbon\Carbon::createFromTimestamp($periodEnd),
                        'stripe_status' => 'active'
                    ];
                    if ($planId) {
                        $updateData['plan_id'] = $planId;
                    }

                    $sub->forceFill($updateData)->save();
                    Log::info("🔄 UPGRADE SUCCESS: Synced new end date for {$stripeId}: " . date('Y-m-d', $periodEnd));
                } else {
                    // FALLBACK: Calculate based on REAL interval & count
                    $calculatedDate = match ($unit) {
                        'year' => now()->addYears($count),
                        'month' => now()->addMonths($count),
                        'week' => now()->addWeeks($count),
                        'day' => now()->addDays($count),
                        default => now()->addMonths($count),
                    };

                    $updateData = [
                        'current_period_end' => $calculatedDate,
                        'stripe_status' => 'active'
                    ];
                    if ($planId) {
                        $updateData['plan_id'] = $planId;
                    }

                    $sub->forceFill($updateData)->save();
                    Log::info("🎯 FIXED SYNC (Updated): Added {$count} {$unit}(s). New date: " . $calculatedDate->toDateString());
                }
            }
        } catch (\Exception $e) {
            Log::error("❌ Upgrade Sync Failed: " . $e->getMessage());
        }

        $endTime = microtime(true);
        Log::info('✅ Webhook END: customer.subscription.updated', [
            'time' => $endTime,
            'duration' => round(($endTime - $startTime) * 1000, 2) . 'ms'
        ]);

        return $response;
    }

    /**
     * Handle the Checkout Session Completed event.
     * This is the most reliable place to capture metadata from the initial checkout.
     */
    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $startTime = microtime(true);
        Log::info('🕐 Webhook START: checkout.session.completed', ['time' => $startTime]);

        $session = $payload['data']['object'];
        $planId = $session['metadata']['plan_id'] ?? null;
        $subscriptionId = $session['subscription'] ?? null;

        if ($subscriptionId && $planId) {
            try {
                // CHEAT CODE: Force retrieve subscription FIRST to get accurate start_date
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
                $subArray = $stripeSubscription->toArray();

                // Extract start_date from Stripe
                $startDate = $subArray['start_date']
                    ? \Carbon\Carbon::createFromTimestamp($subArray['start_date'])
                    : now();

                // Use the raw model to bypass Cashier's internal guards
                $updated = Subscription::where('stripe_id', $subscriptionId)->update([
                    'plan_id' => $planId,
                    'starts_at' => $startDate,
                    'stripe_status' => 'active'
                ]);

                // Continue with period_end sync
                $periodEnd = $subArray['current_period_end'] ?? null;
                $trialEnd = $subArray['trial_end'] ?? null;

                // TRUTH: Check the actual plan interval AND count
                $planObj = $stripeSubscription['items']['data'][0]['plan'] ?? [];
                $unit = $planObj['interval'] ?? 'month';
                $count = $planObj['interval_count'] ?? 1;

                $newSubscription = Subscription::where('stripe_id', $subscriptionId)->first();

                if ($newSubscription) {
                    if ($periodEnd || $trialEnd) {
                        // HAPPY PATH: Stripe gave us the data
                        $newSubscription->forceFill([
                            'current_period_end' => $periodEnd ? \Carbon\Carbon::createFromTimestamp($periodEnd) : null,
                            'trial_ends_at' => $trialEnd ? \Carbon\Carbon::createFromTimestamp($trialEnd) : null,
                            'stripe_status' => 'active'
                        ])->save();

                        Log::info("🏆 WEBHOOK FIXED: Manual retrieve successful for {$subscriptionId}", [
                            'period_end' => $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : 'NULL',
                            'trial_end' => $trialEnd ? date('Y-m-d H:i:s', $trialEnd) : 'NULL',
                            'starts_at' => $startDate->toDateString(),
                        ]);
                    } else {
                        // FALLBACK: Calculated based on REAL interval & count
                        $calculatedDate = match ($unit) {
                            'year' => now()->addYears($count),
                            'month' => now()->addMonths($count),
                            'week' => now()->addWeeks($count),
                            'day' => now()->addDays($count),
                            default => now()->addMonths($count),
                        };

                        $newSubscription->forceFill([
                            'current_period_end' => $calculatedDate,
                            'stripe_status' => 'active'
                        ])->save();

                        Log::info("🎯 FIXED SYNC (Checkout): Added {$count} {$unit}(s). New date: " . $calculatedDate->toDateString());
                    }
                }

                if ($updated) {
                    Log::info("Webhook Success: Plan ID {$planId} linked");
                    // Cleanup old subs
                    $newSubscription = Subscription::where('stripe_id', $subscriptionId)->first();
                    if ($newSubscription) {
                        $oldSubscriptions = Subscription::where('user_id', $newSubscription->user_id)
                            ->where('type', 'default')
                            ->where('stripe_status', 'active')
                            ->where('stripe_id', '!=', $subscriptionId)
                            ->get();

                        foreach ($oldSubscriptions as $oldSub) {
                            $oldSub->update(['stripe_status' => 'canceled', 'ends_at' => now()]);
                        }
                    }
                } else {
                    Log::warning("Webhook Warning: Subscription {$subscriptionId} not found yet.");
                }
            } catch (\Exception $e) {
                Log::error("Webhook Error: " . $e->getMessage());
            }
        }

        $endTime = microtime(true);
        Log::info('✅ Webhook END: checkout.session.completed', [
            'time' => $endTime,
            'duration' => round(($endTime - $startTime) * 1000, 2) . 'ms'
        ]);

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle payment method attached event
     * Syncs payment method details when user adds/updates their card
     */
    protected function handlePaymentMethodAttached(array $payload)
    {
        try {
            $paymentMethod = $payload['data']['object'];
            $customerId = $paymentMethod['customer'] ?? null;

            if ($customerId) {
                $user = \App\Models\User::where('stripe_id', $customerId)->first();

                if ($user && isset($paymentMethod['card'])) {
                    $user->update([
                        'pm_type' => $paymentMethod['card']['brand'] ?? 'card',
                        'pm_last_four' => $paymentMethod['card']['last4'] ?? null,
                    ]);

                    Log::info("Webhook: Payment method attached and synced", [
                        'user_id' => $user->id,
                        'pm_type' => $paymentMethod['card']['brand'],
                        'pm_last_four' => $paymentMethod['card']['last4'],
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Webhook Error (Payment Method Attached): " . $e->getMessage());
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle invoice payment succeeded - (Simplified logging only)
     */
    protected function handleInvoicePaymentSucceeded(array $payload)
    {
        Log::info('🕐 Webhook: invoice.payment_succeeded received (handled via checkout session)');
        return response()->json(['status' => 'success']);
    }
}
