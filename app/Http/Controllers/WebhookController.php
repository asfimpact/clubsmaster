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
        // 1. Let Cashier do its default work first (creates the subscription in DB)
        $response = parent::handleCustomerSubscriptionCreated($payload);

        try {
            // 2. Extract our custom metadata
            $data = $payload['data']['object'];
            $stripeId = $data['id'];

            // Log raw metadata for debugging
            Log::info("Webhook Processing: Subscription {$stripeId}", ['metadata' => $data['metadata'] ?? 'NONE']);

            // Metadata can be sometimes nested in 'metadata' key or directly on object depending on API version
            // Cashier usually ensures metadata is available.
            $planId = $data['metadata']['plan_id'] ?? null;

            if ($planId) {
                // 3. Update our local subscription record with the Plan ID
                // We also forcefully set stripe_status to ensure consistency immediately
                // And explicitly set starts_at to NOW() if it was missed
                $updated = Subscription::where('stripe_id', $stripeId)->update([
                    'plan_id' => $planId,
                    'stripe_status' => 'active',
                    'starts_at' => now(), // Force start date
                ]);

                if ($updated) {
                    Log::info("Webhook Success: Plan ID {$planId} linked to Subscription {$stripeId}");
                } else {
                    Log::error("Webhook Error: Could not find subscription with stripe_id {$stripeId} to update.");
                }
            } else {
                Log::warning("Webhook Warning: No plan_id metadata found for Subscription {$stripeId}");
            }
        } catch (\Exception $e) {
            Log::error("Webhook Metadata Sync Failed: " . $e->getMessage());
        }

        return $response;
    }

    /**
     * Handle subscription updates to keep plan_id in sync if changed via Stripe Dashboard
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        // 1. Let Cashier update the subscription status/dates
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        try {
            $data = $payload['data']['object'];
            $stripeId = $data['id'];
            $planId = $data['metadata']['plan_id'] ?? null;

            if ($planId) {
                Subscription::where('stripe_id', $stripeId)->update([
                    'plan_id' => $planId
                ]);
                Log::info("Webhook Update: Synced plan_id {$planId} for Subscription {$stripeId}");
            }
        } catch (\Exception $e) {
            Log::error("Webhook Update Sync Failed: " . $e->getMessage());
        }

        return $response;
    }

    /**
     * Handle the Checkout Session Completed event.
     * This is the most reliable place to capture metadata from the initial checkout.
     */
    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $session = $payload['data']['object'];
        $planId = $session['metadata']['plan_id'] ?? null;
        $subscriptionId = $session['subscription'] ?? null;

        if ($subscriptionId && $planId) {
            try {
                // Use the raw model to bypass Cashier's internal guards
                // We update the subscription that Cashier (hopefully) just created via the earlier webhook
                $updated = Subscription::where('stripe_id', $subscriptionId)->update([
                    'plan_id' => $planId,
                    'starts_at' => now(),
                    'stripe_status' => 'active'
                ]);

                if ($updated) {
                    Log::info("Webhook Success (Checkout Session): Plan ID {$planId} linked to Subscription {$subscriptionId}");
                } else {
                    Log::warning("Webhook Warning (Checkout Session): Subscription {$subscriptionId} not found yet. It might be created shortly by customer.subscription.created.");
                    // Fallback: If subscription isn't created yet, we rely on handleCustomerSubscriptionCreated picking it up.
                    // But typically, Cashier processes events quickly.
                }
            } catch (\Exception $e) {
                Log::error("Webhook Error (Checkout Session): " . $e->getMessage());
            }
        }

        // Cashier doesn't have a parent method for this event, so we just return success
        return response()->json(['status' => 'success']);
    }
}
