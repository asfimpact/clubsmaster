#### Project Vision & Goals
**Phase 1:**  "Subscription Gateway" – Facilitate WordPress ➔ app.clubsmaster.com transition.

**Core Strategy:** Low-friction registration. Users enter the app first, then "Unlock" features by subscribing.

**Workflow Process**
Step 1: Read this file before every task.
Step 2: Explore the relevant directory to see existing Vuexy patterns.
Step 3: Propose a written implementation plan.
Step 4: Wait for user approval before writing or modifying any code.

**User & Admin Workflows**
1. User Workflow (The "Club Owner")
- WP Entry: User is redirected from WordPress landing page to /register.
- Onboarding: User Registration ➔ Login.
- Verification: Email Verification (Mandatory/Opt-in based on settings).
- Paywall: Landing on a "Restricted Dashboard" ➔ Prompted for Plan Selection.
- Checkout: Stripe Payment Processing.
- Activation: Access to Profile Settings and mandatory club management pages.

2. Admin Workflow (The "Clubsmaster Staff")
Entry: Secure Admin Login.
Setup: Dashboard ➔ Pricing Plan Management ➔ Stripe API Settings.
Operations: User CRM (View user status, plan expiry, active/inactive).
Config: Theme configuration and default system settings.

**Tech Stack**
Backend: Laravel 12 (Sanctum/Fortify for Auth).
Frontend: Vue 3 (Composition API / <script setup>) + Vite.
Template: Vuexy Full Version (strictly followed).

**Database: MySQL via XAMPP.**

**Coding Standards & Strict Rules**
- No Over-Engineering: Follow Vuexy's file-based routing in resources/js/pages/. Do NOT manually edit routes/web.php for Vue pages.
- State Management: Use Pinia (Vuexy standard) for user state and abilities.
- Auth Templates: Use V1 (Centered) layouts from resources/js/views/pages/authentication/.
- Layout Logic: Use meta: { layout: 'blank' } for all Auth-related pages.
- API Calls: Use the built-in @axios instance and useApi composables.
- ACL / Sidebar: Manage visibility in resources/js/navigation/vertical/index.js using action and subject (CASL).

**Technical Reference Paths**
- Routing: Managed by unplugin-vue-router via files in resources/js/pages/.
- Permissions: Defined in resources/js/plugins/casl/ability.js.
- Navigation: Configured in resources/js/navigation/vertical/index.js.
- Components: Custom components go in resources/js/components/, template overrides in resources/js/@core/.

- https://demos.pixinvent.com/vuexy-html-admin-template/documentation/
