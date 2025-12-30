<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add Cashier columns to existing subscriptions table
            if (!Schema::hasColumn('subscriptions', 'type')) {
                $table->string('type')->after('plan_id')->default('default');
            }
            if (!Schema::hasColumn('subscriptions', 'stripe_id')) {
                $table->string('stripe_id')->unique()->nullable()->after('type');
            }
            if (!Schema::hasColumn('subscriptions', 'stripe_status')) {
                $table->string('stripe_status')->nullable()->after('stripe_id');
            }
            if (!Schema::hasColumn('subscriptions', 'stripe_price')) {
                $table->string('stripe_price')->nullable()->after('stripe_status');
            }
            if (!Schema::hasColumn('subscriptions', 'quantity')) {
                $table->integer('quantity')->nullable()->after('stripe_price');
            }
            if (!Schema::hasColumn('subscriptions', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('subscriptions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('trial_ends_at');
            }
        });

        // Add index separately to avoid conflicts
        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['user_id', 'stripe_status']);
            });
        } catch (\Exception $e) {
            // Index already exists, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'stripe_status']);
            $table->dropColumn([
                'type',
                'stripe_id',
                'stripe_status',
                'stripe_price',
                'quantity',
                'trial_ends_at',
                'ends_at',
            ]);
        });
    }
};
