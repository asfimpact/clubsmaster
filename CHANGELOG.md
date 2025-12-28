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
[pending] - Sanctum Auth & Role Integration Client User Login


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
