<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;

class SyncSubscriptionPeriods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync-periods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync current_period_end from Stripe for existing subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Syncing subscription periods from Stripe...');

        // Get all active Stripe subscriptions without current_period_end
        $subscriptions = Subscription::whereNotNull('stripe_id')
            ->whereIn('stripe_status', ['active', 'trialing', 'canceled'])
            ->whereNull('current_period_end')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('✅ No subscriptions need syncing!');
            return 0;
        }

        $this->info("Found {$subscriptions->count()} subscriptions to sync");

        $bar = $this->output->createProgressBar($subscriptions->count());
        $bar->start();

        $synced = 0;
        $errors = 0;

        foreach ($subscriptions as $subscription) {
            try {
                // Fetch from Stripe
                $stripeSubscription = $subscription->asStripeSubscription();

                if ($stripeSubscription->current_period_end) {
                    $subscription->update([
                        'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                    ]);

                    $synced++;
                }

                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\nError syncing subscription {$subscription->id}: " . $e->getMessage());
                $errors++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Synced: {$synced}");
        if ($errors > 0) {
            $this->warn("⚠️  Errors: {$errors}");
        }

        return 0;
    }
}
