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
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('yearly_price', 10, 2)->nullable()->after('price');
            $table->string('stripe_monthly_price_id')->nullable()->after('features');
            $table->string('stripe_yearly_price_id')->nullable()->after('stripe_monthly_price_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['yearly_price', 'stripe_monthly_price_id', 'stripe_yearly_price_id']);
        });
    }
};
