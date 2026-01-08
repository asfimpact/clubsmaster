<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 120;

    /**
     * Webhook event type
     */
    protected $eventType;

    /**
     * Webhook payload
     */
    protected $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(string $eventType, array $payload)
    {
        $this->eventType = $eventType;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🔄 Processing webhook job', [
            'event_type' => $this->eventType,
            'attempt' => $this->attempts(),
        ]);

        try {
            switch ($this->eventType) {
                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded();
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated();
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted();
                    break;

                default:
                    Log::info('Webhook event not handled in queue', [
                        'event_type' => $this->eventType
                    ]);
            }

            Log::info('✅ Webhook job completed successfully', [
                'event_type' => $this->eventType,
                'attempt' => $this->attempts(),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Webhook job failed', [
                'event_type' => $this->eventType,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle invoice payment succeeded
     */
    protected function handleInvoicePaymentSucceeded(): void
    {
        $invoice = $this->payload['data']['object'];
        $customerId = $invoice['customer'] ?? null;

        if (!$customerId) {
            Log::warning('No customer ID in invoice payload');
            return;
        }

        $user = User::where('stripe_id', $customerId)->first();

        if (!$user) {
            Log::warning('User not found for Stripe customer', [
                'stripe_customer_id' => $customerId
            ]);
            return;
        }

        // Clear and warm cache for instant UI update
        CacheService::refreshUser($user->id);

        Log::info('Invoice payment succeeded - cache refreshed', [
            'user_id' => $user->id,
            'invoice_id' => $invoice['id'] ?? 'unknown',
            'amount' => $invoice['amount_paid'] ?? 'unknown',
        ]);
    }

    /**
     * Handle subscription updated
     */
    protected function handleSubscriptionUpdated(): void
    {
        $subscription = $this->payload['data']['object'];
        $customerId = $subscription['customer'] ?? null;

        if (!$customerId) {
            Log::warning('No customer ID in subscription payload');
            return;
        }

        $user = User::where('stripe_id', $customerId)->first();

        if (!$user) {
            Log::warning('User not found for Stripe customer', [
                'stripe_customer_id' => $customerId
            ]);
            return;
        }

        // Clear and warm cache
        CacheService::refreshUser($user->id);

        Log::info('Subscription updated - cache refreshed', [
            'user_id' => $user->id,
            'subscription_id' => $subscription['id'] ?? 'unknown',
            'status' => $subscription['status'] ?? 'unknown',
        ]);
    }

    /**
     * Handle subscription deleted
     */
    protected function handleSubscriptionDeleted(): void
    {
        $subscription = $this->payload['data']['object'];
        $customerId = $subscription['customer'] ?? null;

        if (!$customerId) {
            Log::warning('No customer ID in subscription payload');
            return;
        }

        $user = User::where('stripe_id', $customerId)->first();

        if (!$user) {
            Log::warning('User not found for Stripe customer', [
                'stripe_customer_id' => $customerId
            ]);
            return;
        }

        // Clear cache (subscription is deleted, no need to warm)
        CacheService::clearUser($user->id);

        Log::info('Subscription deleted - cache cleared', [
            'user_id' => $user->id,
            'subscription_id' => $subscription['id'] ?? 'unknown',
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('🚨 Webhook job failed permanently after all retries', [
            'event_type' => $this->eventType,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // TODO: Send alert to admin (email, Slack, etc.)
    }
}
