# 📧 EMAIL NOTIFICATIONS & ACTIVITY TRACKING - IMPLEMENTATION PLAN

**Date:** 2026-01-09  
**Status:** Planning Phase  
**Priority:** Should implement AFTER critical backend fixes  

---

## 🎯 **REQUIREMENTS ANALYSIS**

### **What You Need:**

1. **Email Notifications to Customers**
   - Welcome email on registration
   - Payment confirmation email
   - Subscription activated email
   - Subscription expiring email
   - Subscription cancelled email
   - Plan upgrade/downgrade email

2. **Last Activity Tracking**
   - Track `last_activity_at` on every user action
   - Update automatically via middleware

3. **Admin Monitoring**
   - Admin can see user's last activity
   - Admin can see user engagement
   - Admin can identify inactive users

---

## ✅ **WHAT'S ALREADY DONE**

### **1. Activity Tracking - ✅ COMPLETE**

**File:** `app/Http/Middleware/UpdateLastActivity.php`

```php
// Already exists and works!
if (is_null(Auth::user()->last_activity_at) || 
    Auth::user()->last_activity_at->diffInMinutes(now()) >= 1) {
    Auth::user()->update([
        'last_activity_at' => now()
    ]);
}
```

**Database Column:** ✅ EXISTS
- `users.last_activity_at` (datetime, nullable)
- Casted to datetime in User model

**Middleware:** ✅ REGISTERED
- Updates every 1 minute (prevents excessive DB writes)
- Only for authenticated users

**Status:** ✅ **FULLY IMPLEMENTED - NO WORK NEEDED**

---

### **2. Email Infrastructure - ⚠️ PARTIAL**

**What Exists:**
- ✅ SMTP settings in admin panel
- ✅ Test email functionality
- ✅ 2FA code emails (raw emails)
- ✅ Mail configuration

**What's Missing:**
- ❌ Notification classes
- ❌ Mailable classes
- ❌ Email templates
- ❌ Automated email triggers

**Status:** ⚠️ **INFRASTRUCTURE EXISTS, NEEDS IMPLEMENTATION**

---

## 📊 **CURRENT STATE vs NEEDED**

| Feature | Current | Needed | Status |
|---------|---------|--------|--------|
| **Activity Tracking** | ✅ Complete | Track last activity | ✅ DONE |
| **Admin View Activity** | ❌ No UI | Show in admin panel | ⏳ TODO |
| **Welcome Email** | ❌ None | Send on registration | ⏳ TODO |
| **Payment Email** | ❌ None | Send on payment success | ⏳ TODO |
| **Subscription Email** | ❌ None | Send on activation | ⏳ TODO |
| **Expiry Email** | ❌ None | Send before expiry | ⏳ TODO |
| **Cancellation Email** | ❌ None | Send on cancel | ⏳ TODO |

---

## 🎯 **MY RECOMMENDATION**

### **✅ DO BACKEND FIXES FIRST**

**Why:**
1. **Email notifications depend on clean architecture**
   - Emails should be sent from services, not controllers
   - Fat controllers make it hard to add email logic
   - Need StripeService to trigger payment emails

2. **Activity tracking is already done**
   - Just need admin UI (quick add)
   - No backend work needed

3. **Email implementation is cleaner with services**
   - StripeService → trigger payment email
   - SubscriptionService → trigger subscription emails
   - Clean separation of concerns

**Order:**
```
1. Fix backend (StripeService, Setting model) ← DO THIS FIRST
2. Add admin UI for activity tracking ← QUICK WIN
3. Implement email notifications ← THEN THIS
```

---

## 📋 **IMPLEMENTATION PLAN**

### **Phase 1: Backend Fixes (THIS WEEK)**
**Effort:** 7-9 hours  
**From:** Production Readiness Checklist  

```
✅ Create StripeService (6-8 hours)
✅ Create Setting model (30 minutes)
```

**Why First:**
- Email logic will go in these services
- Clean architecture for email triggers
- Easier to maintain

---

### **Phase 2: Activity Tracking UI (QUICK WIN - 2 HOURS)**
**Effort:** 2 hours  
**Can do:** Anytime (independent)  

#### **2.1 Add Last Activity Column to Admin Members Table**

**File:** `resources/js/views/pages/admin/members/MembersTable.vue` (or similar)

```vue
<template>
  <VDataTable
    :headers="headers"
    :items="members"
  >
    <!-- Existing columns -->
    
    <!-- NEW: Last Activity Column -->
    <template #item.last_activity_at="{ item }">
      <VChip
        :color="getActivityColor(item.last_activity_at)"
        size="small"
      >
        {{ formatLastActivity(item.last_activity_at) }}
      </VChip>
    </template>
  </VDataTable>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'

const headers = [
  // ... existing headers
  { title: 'Last Activity', key: 'last_activity_at', sortable: true },
]

function formatLastActivity(date) {
  if (!date) return 'Never'
  return formatDistanceToNow(new Date(date), { addSuffix: true })
  // Output: "2 hours ago", "3 days ago", etc.
}

function getActivityColor(date) {
  if (!date) return 'error'
  const hoursSince = (Date.now() - new Date(date)) / (1000 * 60 * 60)
  if (hoursSince < 24) return 'success'      // Active today
  if (hoursSince < 168) return 'warning'     // Active this week
  return 'error'                              // Inactive
}
</script>
```

**Benefits:**
- ✅ Admin sees user engagement
- ✅ Color-coded (green = active, red = inactive)
- ✅ Human-readable ("2 hours ago")
- ✅ Sortable column

---

#### **2.2 Add Activity Filter**

```vue
<VSelect
  v-model="activityFilter"
  :items="[
    { title: 'All Users', value: 'all' },
    { title: 'Active Today', value: 'today' },
    { title: 'Active This Week', value: 'week' },
    { title: 'Inactive (7+ days)', value: 'inactive' },
  ]"
  label="Filter by Activity"
/>
```

**Backend Support:**

```php
// app/Http/Controllers/Admin/MemberController.php

public function index(Request $request)
{
    $query = User::query();
    
    // Filter by activity
    if ($request->activity === 'today') {
        $query->where('last_activity_at', '>=', now()->subDay());
    } elseif ($request->activity === 'week') {
        $query->where('last_activity_at', '>=', now()->subWeek());
    } elseif ($request->activity === 'inactive') {
        $query->where('last_activity_at', '<', now()->subWeek())
              ->orWhereNull('last_activity_at');
    }
    
    return $query->with('subscription.plan')->get();
}
```

---

### **Phase 3: Email Notifications (AFTER BACKEND FIXES)**
**Effort:** 8-12 hours  
**Depends on:** Phase 1 (StripeService, etc.)  

#### **3.1 Create Notification Infrastructure**

**Step 1: Create Notification Classes**

```bash
php artisan make:notification WelcomeNotification
php artisan make:notification PaymentSuccessNotification
php artisan make:notification SubscriptionActivatedNotification
php artisan make:notification SubscriptionExpiringNotification
php artisan make:notification SubscriptionCancelledNotification
php artisan make:notification PlanUpgradedNotification
```

**Step 2: Create Email Templates**

```
resources/views/emails/
├── layout.blade.php           (Base template)
├── welcome.blade.php
├── payment-success.blade.php
├── subscription-activated.blade.php
├── subscription-expiring.blade.php
├── subscription-cancelled.blade.php
└── plan-upgraded.blade.php
```

---

#### **3.2 Example: Payment Success Email**

**Notification Class:**

```php
// app/Notifications/PaymentSuccessNotification.php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentSuccessNotification extends Notification
{
    public function __construct(
        protected $invoice,
        protected $plan
    ) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Payment Successful - ClubMaster')
            ->view('emails.payment-success', [
                'user' => $notifiable,
                'invoice' => $this->invoice,
                'plan' => $this->plan,
                'amount' => $this->invoice->total / 100,
                'date' => now()->format('F j, Y'),
            ]);
    }
}
```

**Email Template:**

```blade
{{-- resources/views/emails/payment-success.blade.php --}}

@extends('emails.layout')

@section('content')
<h1>Payment Successful!</h1>

<p>Hi {{ $user->first_name }},</p>

<p>Thank you for your payment. Your subscription to <strong>{{ $plan->name }}</strong> is now active.</p>

<div style="background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>Payment Details</h3>
    <p><strong>Amount:</strong> £{{ number_format($amount, 2) }}</p>
    <p><strong>Plan:</strong> {{ $plan->name }}</p>
    <p><strong>Date:</strong> {{ $date }}</p>
    <p><strong>Invoice ID:</strong> {{ $invoice->id }}</p>
</div>

<p>You can view your subscription details in your dashboard.</p>

<a href="{{ config('app.url') }}/dashboard" style="display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0;">
    View Dashboard
</a>

<p>Thank you for being a valued member!</p>
@endsection
```

---

#### **3.3 Trigger Emails from Services**

**In StripeService (after Phase 1):**

```php
// app/Services/StripeService.php

class StripeService
{
    public function handlePaymentSuccess($invoice, $user)
    {
        // Existing payment logic...
        
        // Send email notification
        $user->notify(new PaymentSuccessNotification(
            $invoice,
            $user->subscription->plan
        ));
        
        Log::info('Payment success email sent', [
            'user_id' => $user->id,
            'invoice_id' => $invoice->id
        ]);
    }
}
```

**In SubscriptionService:**

```php
// app/Services/SubscriptionService.php

class SubscriptionService
{
    public function activateSubscription($user, $plan)
    {
        // Existing activation logic...
        
        // Send activation email
        $user->notify(new SubscriptionActivatedNotification($plan));
    }
    
    public function cancelSubscription($user)
    {
        // Existing cancellation logic...
        
        // Send cancellation email
        $user->notify(new SubscriptionCancelledNotification(
            $user->subscription
        ));
    }
}
```

---

#### **3.4 Scheduled Email: Expiry Warnings**

**Create Command:**

```php
// app/Console/Commands/SendExpiryWarnings.php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Console\Command;

class SendExpiryWarnings extends Command
{
    protected $signature = 'subscriptions:send-expiry-warnings';
    protected $description = 'Send expiry warnings to users whose subscriptions are expiring soon';

    public function handle()
    {
        // Find subscriptions expiring in 7 days
        $users = User::whereHas('subscription', function($query) {
            $query->where('ends_at', '>=', now())
                  ->where('ends_at', '<=', now()->addDays(7))
                  ->where('stripe_status', '!=', 'cancelled');
        })->get();

        foreach ($users as $user) {
            $user->notify(new SubscriptionExpiringNotification(
                $user->subscription
            ));
            
            $this->info("Sent expiry warning to {$user->email}");
        }

        $this->info("Sent {$users->count()} expiry warnings");
    }
}
```

**Schedule in Kernel:**

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Send expiry warnings daily at 9 AM
    $schedule->command('subscriptions:send-expiry-warnings')
             ->dailyAt('09:00');
}
```

---

## 📊 **COMPLETE IMPLEMENTATION TIMELINE**

### **Week 1: Backend Fixes (CRITICAL)**
**Effort:** 7-9 hours  

```
Day 1-2:
✅ Create StripeService (6-8 hours)
✅ Create Setting model (30 minutes)
✅ Test thoroughly (1 hour)
```

**Deliverable:**
- ✅ Clean architecture
- ✅ Ready for email integration
- ✅ 90% production-ready

---

### **Week 1-2: Activity Tracking UI (QUICK WIN)**
**Effort:** 2 hours  

```
Day 3:
✅ Add last_activity_at column to admin table (1 hour)
✅ Add activity filter (30 minutes)
✅ Add color coding (30 minutes)
```

**Deliverable:**
- ✅ Admin can see user activity
- ✅ Can filter inactive users
- ✅ Visual engagement indicators

---

### **Week 2-3: Email Notifications (MAIN FEATURE)**
**Effort:** 8-12 hours  

```
Day 1:
✅ Create notification classes (2 hours)
✅ Create email templates (3 hours)

Day 2:
✅ Integrate with StripeService (2 hours)
✅ Integrate with SubscriptionService (2 hours)

Day 3:
✅ Create expiry warning command (1 hour)
✅ Schedule command (30 minutes)
✅ Test all emails (2 hours)
```

**Deliverable:**
- ✅ All customer emails automated
- ✅ Professional email templates
- ✅ Scheduled expiry warnings

---

## 🎯 **FINAL RECOMMENDATION**

### **DO THIS ORDER:**

**1. Backend Fixes (THIS WEEK) - 7-9 hours**
```
Priority: 🔴 CRITICAL
Why: Clean architecture for email integration
Blocks: Email notifications
```

**2. Activity Tracking UI (ANYTIME) - 2 hours**
```
Priority: 🟢 LOW (can do in parallel)
Why: Independent feature, quick win
Blocks: Nothing
```

**3. Email Notifications (NEXT WEEK) - 8-12 hours**
```
Priority: 🟡 MEDIUM
Why: Depends on clean services
Blocks: None (but improves UX)
```

---

### **WHY THIS ORDER:**

**Backend First:**
- ✅ Email logic belongs in services
- ✅ Fat controllers make email integration messy
- ✅ Clean architecture = easier email implementation
- ✅ Prevents technical debt

**Activity UI Anytime:**
- ✅ Already works (just needs UI)
- ✅ Independent of backend fixes
- ✅ Quick win for admin

**Emails Last:**
- ✅ Needs clean services to trigger from
- ✅ Easier to implement with StripeService
- ✅ More maintainable

---

## ✅ **WHAT YOU GET**

### **After Backend Fixes:**
- ✅ Clean architecture
- ✅ Ready for email integration
- ✅ 90% production-ready

### **After Activity UI:**
- ✅ Admin sees user engagement
- ✅ Can identify inactive users
- ✅ Better monitoring

### **After Email Notifications:**
- ✅ Professional customer communication
- ✅ Automated email triggers
- ✅ Scheduled expiry warnings
- ✅ 100% complete SaaS experience

---

## 📝 **SUMMARY**

**Current State:**
- ✅ Activity tracking: DONE (just needs UI)
- ⚠️ Email infrastructure: EXISTS (needs implementation)

**Recommendation:**
1. ✅ Do backend fixes first (7-9 hours)
2. ✅ Add activity UI anytime (2 hours)
3. ✅ Implement emails after (8-12 hours)

**Total Effort:** 17-23 hours for complete implementation

**Why Seamless:**
- Backend fixes make email integration clean
- Activity tracking already works
- Proper separation of concerns
- Maintainable long-term

---

**Implementation plan complete!** 📧✅
