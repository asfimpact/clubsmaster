#### [2025-12-30] - Stripe Checkout , Free Plan Integration & logic
- **Stripe Checkout Integration**
  - Added `StripeController` to handle secure checkout session creation.
  - Implemented `/stripe/checkout` API endpoint.
  - Configured success (`/?payment=success`) and cancel (`/pricing?payment=cancelled`) redirect flows.
- **Free Plan Activation**
  - Added dynamic duration calculation for free trials (Monthly vs Yearly).
  - Implemented confirmation modal on the pricing page.
  - Enabled direct database subscription creation for free plans (bypassing Stripe).
- **User Dashboard Enhancements**
  - Added `current_plan` accessor to the `User` model for frontend usage.
  - Implemented **“Your Current Plan”** badge logic on pricing cards.
  - Added Snackbar notifications for payment success and cancellation.
### 🔧 Backend
- **Models**
  - `app/Models/User.php`
    - Added `Billable` trait.
    - Implemented `getCurrentPlanAttribute`.
  - `app/Models/Subscription.php`
    - Extended Laravel Cashier’s `Subscription` model.
- **Controllers**
  - `app/Http/Controllers/User/SubscriptionController.php`
    - Updated to use strict Cashier-compatible fields (`stripe_status = 'active'`).
  - `app/Http/Controllers/StripeController.php`
    - Added guard clauses to prevent free plans from calling the Stripe API.
- **Routes**
  - `routes/api.php`
    - Added `/api/stripe/checkout` route protected by Sanctum.
- **Migrations**
  - Added Laravel Cashier tables:
    - `database/migrations/*_create_customers_table.php`
    - `database/migrations/*_create_subscriptions_table.php`
    - `database/migrations/*_create_subscription_items_table.php`
  - Renamed `expires_at` column to `ends_at`.
### 💻 Frontend
- **Components**
  - `resources/js/pages/AppPricing.vue`
    - Integrated `useApi` for dynamic data loading.
    - Added loading states and button feedback.
    - Implemented success and error handling for subscription flows.
    - Added `VSnackbar` toast notifications.
- **Utilities**
  - `resources/js/composables/useApi.js`
    - Fixed JSON payload handling and `Content-Type` headers.
### 📁 Files Modified / Added
- `app/Http/Controllers/StripeController.php`
- `app/Http/Controllers/User/SubscriptionController.php`
- `app/Models/User.php`
- `app/Models/Subscription.php`
- `routes/api.php`
- `database/migrations/*`files
- `resources/js/pages/AppPricing.vue`
- `resources/js/composables/useApi.js`
**Commit Message**
[pending] - Stripe Checkout , Free Plan Integration & logic

#### [29-12-2025] - Profile and account settins changes, Plan management and Subscription Management System implementation and enhancements for future integration with other systems.
## 📊 Added Database
- **Plans Table Enhancements**:
  - Added `features` (JSON) column for dynamic feature lists
  - Added `tagline` (String) column for plan descriptions
  - Added `yearly_price` (Decimal) column for yearly pricing
  - Added `yearly_duration_days` (Integer, default 365) as Source of Truth for yearly subscription validity
  - Added `stripe_monthly_price_id` and `stripe_yearly_price_id` for payment gateway integration.
# 🔧 Backend API & Controllers
- **Authentication & Profile**
  - **AuthController**:
    - Added `updateProfile` method to handle user name and phone number updates via `/auth/profile-update` endpoint
- **User Controllers (User Namespace)**
  - **User\PlanController**:
    - Created public API endpoint (`/user/plans`) to fetch plans dynamically for users
  - **User\SubscriptionController**:
    - Created to handle subscription management logic
    - Implements expiry date calculations based on database validity columns (`duration_days` for monthly, `yearly_duration_days` for yearly)
    - Handles plan selection and subscription assignment
  - **User\BillingController**:
    - Created to calculate and display subscription health metrics:
      - `days_consumed`
      - `days_remaining`
      - `progress_percent`
    - Handles "No Active Plan" states
- **Admin Controllers**
  - **Admin\PlanController**:
    - Updated to process and validate new pricing fields:
      - `Tagline`
      - `Yearly Price`
      - `Yearly Validity Days`
      - `Features` (converts textarea new-lines to JSON arrays)
      - `Stripe Price IDs` (Monthly/Yearly)
- **Security Features**
  - **Change Password**:
    - Implemented full backend logic (`changePassword` method)
    - Validates minimum 8 characters and matching confirmation
- **Changed**:
  - **Subscription Logic**:
    - Removed hardcoded yearly price calculation (previously monthly price × 10)
    - Now uses `yearly_price` directly from the database
    - Expiry calculations now use explicit database validity columns instead of hardcoded values
    - Subscription logic properly checks against `plan_id` to ensure correct plan activation
## 🔒 Security & Middleware
- **CheckSubscription Middleware**:  NEW
  - Created to verify active subscription status (checks `end_date` vs `now()`).
  - Uses `hasActiveSubscription()` method from the User model
  - Returns 403 Forbidden for expired or missing subscriptions
  - Registered as `subscribed` alias in `bootstrap/app.php`
  - **Status**: Available but not applied to any routes yet (inactive until manually assigned)
  - **User Model**:
    - Added `hasActiveSubscription()` helper method to check subscription validity by comparing `end_date` with the current date
  - **Plan Model**:
    - Updated with new fillable fields and array casting for `features` column
## 🎨 Frontend - User Dashboard
- **Profile & Account Settings**
  - **AccountSettingsAccount.vue**:
    - Refactored to fetch real user data from `/user` endpoint
    - Connected to `/auth/profile-update` for saving changes
    - Removed demo fields (Language, Organization)
- **Security Tab**
  - **Change Password**:
    - Full frontend implementation with form validation
    - Min 8 characters requirement
    - Matching password confirmation
  - **Two-Factor Authentication**:
    - UI cleaned to show "Admin Managed" status (via Global Email OTP)
    - Removed misleading "Enable Authenticator App" buttons
    - Removed static "API Keys" section
- **Billing Tab**
  - **Current Plan Card**:
    - Refactored to display live subscription data:
      - Plan Name
      - Price
      - Expiry Date
      - Days Remaining
      - Progress Bar
    - Handles "No Active Plan" states dynamically
    - Standardized to GBP (£) currency
## Changed
- **Navigation**:
  - Updated "Profile" link in avatar dropdown to use real User ID
  - Removed redundant "Profile" menu item
## 💳 Frontend - Pricing & Subscription UI
- **AppPricing.vue**:
  - Dynamic data fetching from `/user/plans` API (replaced hardcoded arrays)
  - Smart billing toggle between Monthly and Yearly pricing using real DB values
  - Real-time plan feature rendering from database
  - Dynamic tagline display
  - "Select Plan" buttons now trigger real subscriptions via API.
  - "Current Plan" badge logic updated to check `user.subscription_id`.
## Changed
- **Pricing Display Logic**:
  - **Yearly Mode**: Shows actual yearly price from the database
  - **Monthly Mode**: Shows monthly price from the database
  - Removed redundant pricing sub-text (e.g., "USD 499/Year")
  - Shows "Billed Monthly" or "Billed Yearly" based on toggle selection
- **Plan Selection Flow**:
  - "Select Plan" button now triggers real subscription via API
  - Dynamic button states:
    - "Your Current Plan" (disabled/green) for active subscriptions
    - "Select Plan" or "Subscribe" for available plans
  - Badge logic updated to check `user.subscription_id`
  - Only shows "Your Current Plan" badge when subscription matches selected plan
## 🛠 Admin Panel - Pricing Management
- **Pricing Management UI**:
  - Input field for `Tagline`
  - Input field for `Yearly Price`
  - Input field for `Yearly Validity (Days)`
  - Textarea for `Features` (auto-converts new-lines to JSON)
  - Input fields for `Stripe Monthly Price ID`
  - Input field for `Stripe Yearly Price ID`
  - Added "Yearly Validity (Days)" input to control subscription duration explicitly.
  - Added "Features" Textarea that automatically converts new-line lists into JSON arrays.
  - Added fields for Stripe Price IDs (Monthly/Yearly).
  - **Logic**: Updated `Admin\PlanController` to process and validate all new fields.
## Changed
- **Data Processing**:
  - Admin can now edit all pricing fields dynamically
  - Features automatically formatted as JSON arrays
  - All fields validated and processed by `Admin\PlanController`
## 🐛 Bug Fixes
- **Pricing Display**:
  - Fixed incorrect yearly price calculation when toggling between monthly/yearly
  - Removed redundant text (e.g., extra "GBP 499/Year" below main price)
  - Ensured yearly price comes directly from database
- **Subscription Logic**:
  - Fixed badge logic to correctly match plan ID with active subscription
  - Corrected subscription expiry calculations using proper database columns
- **UI/UX**:
  - Cleaned up pricing display for better clarity and user experience
  - Removed conflicting or duplicate pricing information
## 📝 System Status & Notes
- **Subscription Awareness**:
  - System is fully aware of subscription validity
  - All logic implemented but remains permissive (no routes blocked)
  - Middleware registered but not active on any routes
## Future Readiness
- **Payment Integration**:
  - Database prepared for Stripe integration with price ID fields
  - Ready for future payment gateway connections
- **Third-Party Integration**:
  - Architecture supports future integration with:
    - Firebase
    - Scorer App
    - Other subscription management systems
## Important Notes
- **No Structural Changes**: Subscriptions table structure remains unchanged (only logic updated)
- **Middleware Activation**: `CheckSubscription` middleware will be applied manually to routes when needed
- **Currency Standard**: GBP (£) standardized across all user and admin interfaces
**Files Modified:**
- app/Http/Controllers/Admin/PlanController.php
- app/Http/Controllers/AuthController.php
- app/Models/Plan.php
- app/Models/User.php
- bootstrap/app.php
- components.d.ts
- config/auth.php
- resources/js/components/AppPricing.vue
- resources/js/layouts/components/UserProfile.vue
- resources/js/pages/admin/pricing-mgmt.vue
- resources/js/views/pages/account-settings/AccountSettingsAccount.vue
- resources/js/views/pages/account-settings/AccountSettingsBillingAndPlans.vue      
- resources/js/views/pages/account-settings/AccountSettingsSecurity.vue
- routes/api.php
**Files Added:**
- app/Http/Controllers/User/ BillingController.php, planController.php, SubscriptionController.php
- app/Http/Middleware/CheckSubscription.php
- database/migrations/2025_12_29_215327_add_features_and_tagline_to_plans_table.php
- database/migrations/2025_12_30_103925_add_yearly_and_stripe_ids_to_plans_table.php
- database/migrations/2025_12_30_121107_add_yearly_validity_to_plans_table.php
**Commit Message:** Profile and account settins changes, Plan management and Subscription Management System implementation and enhancements for future integration with other systems.

## [29-12-2025] -  2FA Email OTP Security System, Email Server config in admin & Mobile field in signup page Integration and fixes
**Key Features:** Email one-time password (OTP) System, SMTP Configuration, Mobile Number Support, and Router Security Hardening.
# Backend Changes
- **[app/Http/Controllers/AuthController.php]
    - Updated [login] and [register] to automatically trigger 2FA code generation (server-side security).
    - Added validation and storage for the new `phone` field during registration.
    - Updated responses to include `mobile` in the `userData` object.
- **[app/Http/Controllers/Auth/TwoFactorController.php]
    - Created controller to handle OTP verification and manual code resends.
    - Implemented dynamic SMTP configuration (reads from DB instead of .env).
- **[app/Http/Controllers/Admin/SettingController.php]
    - Added `testEmail` method to valid real-time SMTP connections.
- **[app/Models/User.php]
    - Added `two_factor_code` and `two_factor_expires_at` to `$fillable`.
    - Added `two_factor_expires_at` into `$casts` (Carbon instance).
    - Refined `computed_status` to trap `Pending` (unverified email) and `Inactive` (unverified 2FA) users.
- **[database/migrations/2025_12_29_141324_add_2fa_codes_to_users_table.php] (New)**
    - Migration to support persistent OTP storage and expiration times.
### Frontend Changes
- **[resources/js/pages/admin/email-settings.vue] (New)**
    - Full interface for configuring SMTP Host, Port, User, Password, and Encryption.
    - Added "Test Connection" card with real-time success/error feedback.
- **[resources/js/pages/admin/security-mgmt.vue]
    - Renamed toggle to "Require 2FA (Email OTP)" for technical accuracy.
- **[resources/js/pages/pages/authentication/two-steps-v1.vue] 
    - Transformed from static demo to functional Verification Gate.
    - Removed auto-trigger (fixed double-email bug).
    - Added "Hard Refresh" logic to ensure Router Guards respect the new active status.
- **[resources/js/plugins/1.router/guards.js]
    - **Security Fix:** Added strict redirection for unauthenticated users (stops "Ghost Client" access).
    - **Logic:** Implemented the "Iron Gate" that traps `Pending`/`Inactive` users in the 2FA flow.
- **[resources/js/pages/register.vue]
    - Added "Mobile Number" input field to the signup form.
- **[resources/js/layouts/components/UserProfile.vue]
    - Added logic to display the user's mobile number under their name.
- **[resources/js/navigation/vertical/admin.js]
    - Added "Email Server" to the System Configuration menu.
Here’s a cleaned-up version with all repeated lines removed:
**Commit**
[50e4c1f]- 2FA Email OTP Security System, Email Server config in admin & Mobile field in signup page Integration and fixes


[28-12-2025] - Admin Member Management, Pricing Architecture, and Security Pulse Logic
- Key Features: Headless Member Management, Plan CRUD, and User Activity Tracking.
- Advanced Filtering & Search: Added reactive status filters (Active, Suspended, Pending, Inactive) with real-time debounced search.
- Billing Architecture: Created relational database with plans and subscriptions tables. Implemented Laravel Eager Loading to ensure N+1 performance protection.
- Pricing Management: Built full CRUD interface for Admins to manage membership tiers (Price, Duration, Name), with safety checks for active subscriptions.
🔐 Security & "Pulse" Tracking
- Dynamic Security Status: Refactored user status into a computed_status virtual attribute. Status now reflects real-time security logic: Suspended, Pending, Inactive, or Active.
- Global Security Toggle: Created a Security Settings page with global 2FA switch. Toggling recalibrates user access statuses across the portal.
- User Pulse Middleware: Implemented UpdateLastActivity middleware to track activity time with 1-minute throttle for performance.
- Headless Export: Implemented browser-side CSV export for member lists without server load.
🛠️ Technical Improvements
- Development Standards: Established DEVELOPMENT_STANDARDS.md to mandate Eager Loading and high-security architectural rules.
- Vuetify I18n Patch: Resolved raw key displays in en.json.
- Safety Dialogs: Integrated theme's ConfirmDialog for sensitive operations (Suspend, Individual Delete, Bulk Delete).
- Clean DB Schema: Removed redundant last_login_at columns and consolidated activity tracking.
Backend Changes
- [app/Http/Controllers/Admin/MemberController.php]
- Implemented index (list), update (status), and destroy (delete) methods.
- Added "Bulk Delete" logic.
- [app/Http/Controllers/Admin/PlanController.php]
- CRUD operations for Membership Plans.
- [app/Http/Middleware/UpdateLastActivity.php]
- Middleware to track last_activity_at (User Pulse) with 1-minute throttling.
- database/migrations/2025_12_28_..._remove_redundant_login_column.php
- Removed last_login_at in favor of the more accurate last_activity_at.
Frontend Changes
- [resources/js/pages/admin/members/index.vue]
- VDataTable implementation with Status Chips, Search, and Bulk Actions.
- [resources/js/pages/admin/pricing-mgmt.vue]
- UI for creating and managing Membership Plans.
- [resources/js/pages/admin/security-mgmt.vue]
- Initial UI for Global Security Settings (2FA Toggle).
Admin Members Dashboard: High-performance Member Management using VDataTable. Includes Name/Avatar display, Email-Phone merged cells, and responsive action menus (View, Edit, Suspend, Delete).
📁 Files Modified/Added
Backend (Laravel):
- app/Http/Controllers/Admin/MemberController.php
- app/Http/Controllers/Admin/PlanController.php (NEW)
- app/Http/Controllers/Admin/SettingController.php (NEW)
- app/Http/Middleware/UpdateLastActivity.php (NEW)
- app/Models/User.php
- app/Models/Plan.php (NEW)
- app/Models/Subscription.php (NEW)
- database/migrations/* (5 New Migrations)
app/Http/Controllers/AuthController.php
- Updated login and register methods to automatically trigger 2FA code generation.
- Added phone field validation and storage in register.
- Added mobile field to the returned userData object.
app/Http/Controllers/Auth/TwoFactorController.php (New)
- Created controller to handle verify (code checking) and send (manual resends).
- Implemented dynamic SMTP configuration using database settings.
app/Http/Controllers/Admin/SettingController.php
- Added testEmail method to validate SMTP credentials.
- Updated to support email server configuration fields.
app/Models/User.php
- Added two_factor_code and two_factor_expires_at to $fillable.
- Implemented computed_status accessor for dynamic status calculation (Active/Inactive/Pending).
database/migrations/2025_12_29_141324_add_2fa_codes_to_users_table.php (New)
- Created migration to add 2FA logic columns to users table.
Frontend (Vue):
- resources/js/pages/admin/members/index.vue (NEW)
- resources/js/pages/admin/pricing-mgmt.vue (NEW)
- resources/js/pages/admin/security-mgmt.vue (NEW)
- resources/js/navigation/vertical/admin.js (NEW)
- resources/js/@core/utils/formatters.js
- resources/js/plugins/i18n/locales/en.json
- resources/js/pages/admin/email-settings.vue (NEW)
- Created Admin UI for configuring SMTP server details.
- Added "Test Connection" functionality.
- resources/js/pages/admin/security-mgmt.vue
- Updated labels for "Require 2FA (Email OTP)".
- Refined descriptions for clarity.
- resources/js/pages/pages/authentication/two-steps-v1.vue
- Removed auto-trigger from onMounted.
- Disabled auto-submit on input finish.
- Implemented "Hard Refresh" redirect logic for reliability.
- resources/js/pages/register.vue
- Added "Mobile Number" input field.
- Updated script to handle phone data submission.
- resources/js/layouts/components/UserProfile.vue
- Updated template to display userData.mobile below the user's name.
- resources/js/navigation/vertical/admin.js
- Added "Email Server" navigation item.
- resources/js/plugins/1.router/guards.js
- Implemented strict redirection for unauthenticated users.
- Added guard to trap Pending/Inactive users in the 2FA flow.
- resources/js/pages/index.vue
- Updated logic to redirect Pending users to 2FA page.
- resources/js/plugins/i18n/locales/en.json
- Added localization keys for "Email Server" and updated Security Settings labels.
- routes/api.php
- Registered auth/2fa-send and auth/2fa-verify routes.
- Registered admin/settings/test-email route.
- typed-router.d.ts
- Updated by the system to reflect new routes (auto-generated).
Commit
[c7b846d] - Admin Member Management, Pricing Architecture, and Security Pulse Logic.


#### [2025-12-28] - Client Portal & Simplest ACL Migration
🚀 Major Improvements
- Client Dashboard (Portal): Created a dedicated landing page at / specifically for Clients. Features KPI cards for Membership Plan details, Expiry Dates, and a direct integration of the theme's Pricing component.
- Simplest Vuexy Standard ACL: Refactored the entire permission system to follow a lean "Standard" approach. Any page or menu item without explicit ACL meta is now shared by default, eliminating over-engineering.
- Permanent Admin Seeder: Implemented updateOrCreate logic in DatabaseSeeder.php to establish a permanent admin account (admin@clubmaster.com) for testing.
- Centralized Backups: Moved all original navigation and demo files to a root /backups directory and added it to .gitignore to keep the codebase clean.
🔐 Authentication & UI
- Smart Redirection: Implemented a "Traffic Controller" logic in the home page. Admins are automatically pushed to the CRM Dashboard, while Clients land on their personal Portal.
- Sidebar Cleanup: Restructured the side navigation into three focused business sections: Dashboard, Business Management, and System Settings.
- Translation Fixes: Added missing i18n keys to en.json to resolve console warnings for the new custom sidebar sections.
- Auth Data Handshake: Verified the userAbilityRules transmission between Laravel and Vue, ensuring the sidebar renders instantly upon login.
🛠️ Technical Fixes
- CASL Utility Fix: Resolved a critical bug in casl.js by importing getCurrentInstance from Vue. This fixed a silent crash that was preventing the sidebar from rendering.
- Router Collision: Removed manual root redirection in additional-routes.js that was conflicting with the file-based router.
- Shared Access: Updated the can and canNavigate utilities to allow access to "blank" meta pages, ensuring a smooth multi-role experience.
📁 Files Modified/Added
Backend:
app/Http/Controllers/AuthController.php
database/seeders/DatabaseSeeder.php
Navigation:
resources/js/navigation/vertical/index.js
resources/js/navigation/vertical/dashboard.js
resources/js/navigation/vertical/business.js (NEW)
resources/js/navigation/vertical/system.js (NEW)
Pages & Components:
resources/js/pages/index.vue (NEW)
resources/js/pages/business-membership-history.vue (NEW)
resources/js/pages/business-payment-history.vue (NEW)
resources/js/pages/dashboards/analytics.vue
Core Plugins:
resources/js/@layouts/plugins/casl.js
resources/js/plugins/i18n/locales/en.json
resources/js/plugins/1.router/additional-routes.js
**Commit**
[2d7c9e0] - Client Portal Dashboard, Sidebar Cleanup, and Simplest ACL Migration

## [2025-12-28] - Sanctum Auth & Role Integration Client User Login
### 🚀 Major Improvements
- **Real Backend Integration:** Shifted from Mock API (MSW) to a live Laravel 12 backend. Authenticated requests now hit the real MySQL database.
- **Sanctum Authentication:** Fully implemented Laravel Sanctum. Created [AuthController.php] to handle secure registration and login with token-based sessions.
- **Enhanced User Schema:** Updated database to support specialized fields: `first_name`, `last_name`, and `role` (Default: `client`).
- **Dynamic ACL & Permissions:** Integrated CASL permissions between Laravel and Vue 3. New users now receive automatic 'Read' abilities for the dashboard.
### 🔐 Authentication & UI
- **Fixed V1 Register Logic:** Added First/Last name inputs to the centered-card layout and mapped them to the database.
- **Form Validation:** Improved frontend validation for email uniqueness, password length (8+ chars), and Privacy Policy acceptance.
- **Redirection Fix:** Resolved a critical bug where users were redirected to `not-authorized` or `access-control`. Users now land directly on the **Analytics Dashboard** upon entry.
### 🛠️ Technical Fixes
- **MSW Permanent Disable:** Commented out `worker.start()` in the fake-api plugin to prevent API interception.
- **Safe Password Hashing:** Fixed a "double-hashing" bug in the registration flow that was causing 401 Unauthorized errors for new users.
- **CASL Reactivity:** Updated [plugins/casl/index.js]to use a shared ability instance, ensuring the UI updates immediately after login without a page refresh.
- **Page Meta ACL:** Added explicit `action` and `subject` meta to [dashboards/analytics.vue]to bridge the gap with the theme's navigation guards.
### 📁 Files Modified/Added
- **Backend:** 
- app/Http/Controllers/AuthController.php
- app/Models/User.php
- routes/api.php
- bootstrap/app.php
- migrations/*.php
- sanctum.php
- **Frontend:** 
- resources/js/pages/login.vue
- resources/js/pages/register.vue
- resources/js/pages/dashboards/analytics.vue
- resources/js/navigation/vertical/dashboard.js
- resources/js/plugins/1.router/additional-routes.js
- resources/js/plugins/casl/index.js
- resources/js/plugins/fake-api/index.js
**Commit**
[e3b0099] - Sanctum Auth & Role Integration Client User Login


# [27-12-2025] - Changelog - Clubsmaster Project
All notable changes to this project will be documented in this file.
## [Phase 1: Authentication & Project Setup] - 2025-12-27
### Added
- `CLAUDE.md`: Project vision, tech stack, and strict coding rules.
- `VUEXY_CUSTOMIZATION_GUIDE.md`: Comprehensive guide on the correct method to customize the Vuexy full version theme.
- `resources/js/pages/reset-password.vue`: Added V1 centered card layout for password reset.
- `resources/js/pages/verify-email.vue`: Added V1 centered card layout for email verification.
### Changed
- `resources/js/pages/login.vue`: Replaced V2 split-screen layout with V1 centered card layout. Integrated full authentication logic (API calls, form validation, error handling, ability/cookie management).
- `resources/js/pages/register.vue`: Replaced V2 split-screen layout with V1 centered card layout. Added form validation and simplified internal routing links.
- `resources/js/pages/forgot-password.vue`: Replaced V2 split-screen layout with V1 centered card layout. Integrated form validation and simplified internal routing links.
- `.gitignore`: Added `VUEXY_CUSTOMIZATION_GUIDE.md` to ignore list.
- `typed-router.d.ts`: Automatically updated by `unplugin-vue-router` to reflect new authentication routes.
### Summary of Improvements
- **UI Uniformity:** All authentication pages now consistently use the "V1" centered card layout with decorative background shapes.
- **Simplified Routing:** Followed Vuexy's file-based routing convention, avoiding complex manual route overrides.
- **Functionality:** Restored core login functionality to the V1 templates which were originally UI-only demos.
**Commit**
[1f200ee] - all inital pages pointed to v1 centered card layout and added functionality to login, register, reset, verify email, and forgot password pages.
