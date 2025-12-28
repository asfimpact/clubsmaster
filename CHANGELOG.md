### [2025-12-28] - Admin Member Management, Pricing Architecture & Security Pulse
# Major Improvements
- **Admin Members Dashboard:** Implemented a high-performance Member Management module using `VDataTable`. Includes Name/Avatar display, Email-Phone merged cells, and responsive action menus (View, Edit, Suspend, Delete).
- **Advanced Filtering & Search:** Added reactive status (Active, Suspended, Pending, Inactive) and Membership Plan filters with real-time search debounced for performance.
- **Billing Architecture:** Created a relational database system with `plans` and `subscriptions` tables. Implemented the **Laravel Eager Loading** standard (`with(['subscription.plan'])`) to ensure N+1 performance protection.
- **Pricing Management:** Built a full CRUD interface for Admins to manage membership tiers (Price, Duration, Name) with integrated safety checks for active subscriptions.
### 🔐 Security & "Pulse" Tracking
- **Dynamic Security Status:** Refactored user status into a `computed_status` virtual attribute. Status now reflects real-time security logic: **Suspended** (Admin flag), **Pending** (Email verification), **Inactive** (Global 2FA required but missing), or **Active**.
- **Global Security Toggle:** Created a Security Settings page with a global 2FA switch. Toggling this instantly recalibrates user access statuses across the entire portal.
- **User Pulse Middleware:** Implemented `UpdateLastActivity` middleware to track "Time Ago" activity (e.g., "5 mins ago") in the Admin table, with a 1-minute throttle for DB performance.
- **Headless Export:** Implemented a browser-side CSV export utility to download filtered member lists without impacting server load.
### 🛠️ Technical Improvements
- **Development Standards:** Established `DEVELOPMENT_STANDARDS.md` to mandate Eager Loading and high-security architectural rules.
- **Vuetify I18n Patch:** Resolved raw key displays (e.g., `$vuetify.dataIterator.loadingText`) by mapping missing translations in `en.json`.
- **Safety Dialogs:** Integrated the theme's `ConfirmDialog` for all sensitive operations (Suspend, Individual Delete, Bulk Delete).
- **Clean DB Schema:** Removed redundant `last_login_at` columns and consolidated activity tracking.
### 📁 Files Modified/Added
- **Backend (Laravel):**
  - app/Http/Controllers/Admin/MemberController.php
  - app/Http/Controllers/Admin/PlanController.php (NEW)
  - app/Http/Controllers/Admin/SettingController.php (NEW)
  - app/Http/Middleware/UpdateLastActivity.php (NEW)
  - app/Models/User.php
  - app/Models/Plan.php (NEW)
  - app/Models/Subscription.php (NEW)
  - database/migrations/* (5 New Migrations)
- **Frontend (Vue):**
  - resources/js/pages/admin/members/index.vue (NEW)
  - resources/js/pages/admin/pricing-mgmt.vue (NEW)
  - resources/js/pages/admin/security-mgmt.vue (NEW)
  - resources/js/navigation/vertical/admin.js (NEW)
  - resources/js/@core/utils/formatters.js
  - resources/js/plugins/i18n/locales/en.json
**Commit**
[pending] - Admin Member Management, Pricing Architecture, and Security Pulse Logic.

[2025-12-28] - Client Portal & Simplest ACL Migration
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
