<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlanAndSubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Plans
        $plans = [
            ['name' => 'Basic', 'price' => 10.00, 'duration_days' => 30],
            ['name' => 'Standard', 'price' => 25.00, 'duration_days' => 30],
            ['name' => 'Gold', 'price' => 50.00, 'duration_days' => 90],
            ['name' => 'Pro', 'price' => 100.00, 'duration_days' => 365],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(['name' => $planData['name']], $planData);
        }

        $allPlans = Plan::all();

        // 2. Create some dummy clients if they don't exist
        $clients = [
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'role' => 'client', 'phone' => '+1234567890'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com', 'role' => 'client', 'phone' => '+0987654321'],
            ['first_name' => 'Bob', 'last_name' => 'Pending', 'email' => 'bob@example.com', 'role' => 'client', 'phone' => null],
        ];

        foreach ($clients as $c) {
            $user = User::updateOrCreate(
                ['email' => $c['email']],
                [
                    'first_name' => $c['first_name'],
                    'last_name' => $c['last_name'],
                    'password' => Hash::make('password'),
                    'role' => $c['role'],
                    'phone' => $c['phone'],
                    'email_verified_at' => ($c['first_name'] === 'Bob') ? null : now(),
                    'last_activity_at' => now()->subMinutes(rand(1, 1000)),
                ]
            );

            // Assign a subscription
            if ($user->email !== 'bob@example.com') {
                $plan = $allPlans->random();
                Subscription::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'plan_id' => $plan->id,
                        'starts_at' => now()->subDays(10),
                        'expires_at' => rand(0, 1) ? now()->addDays(20) : now()->subDays(2), // Randomly expired
                    ]
                );
            }
        }
    }
}
