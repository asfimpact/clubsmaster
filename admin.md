# Admin Task 1: Member Management - Implementation Plan

This document outlines the lean, "Headless API" approach for implementing Member Management for Administrators.

## 🎯 Business Objectives
*   **Visibility:** Admin can see a list of all registered clients.
*   **Control:** Admin can toggle a member's status (Active/Suspended).
*   **Professionalism:** Use enterprise-grade components (VDataTable) for a premium feel.

---

## 🏗️ 1. Sidebar Restructuring
We will reorganize the Admin sidebar to distinguish between "Business" and "System" logic.

**New Hierarchy for Admin:**
*   **Section: DASHBOARDS** (Shared)
    *   Analytics (Home)
*   **Section: BUSINESS MANAGEMENT** (Admin Only - `subject: 'Admin'`)
    *   Members (COMPLETE ✅)
*   **Section: SYSTEM CONFIGURATION** (Admin Only - `subject: 'Admin'`)
    *   Pricing Plans (COMPLETE ✅)
    *   Security Settings (COMPLETE ✅)
*   **Section: SUPPORT & SETTINGS** (Shared)
    *   Account Settings
    *   FAQ
    *   Support

---

## ⚙️ 2. Backend (Laravel API)
We follow a clean, controller-based architecture.

*   **Controller:** `app/Http/Controllers/Admin/MemberController.php`
*   **Endpoints:**
    *   `GET /api/admin/members`: Returns a JSON list of all users where `role = 'client'`.
    *   `PATCH /api/admin/members/{id}`: Updates member status (Active/Suspended).
*   **Security:**
    *   Protected by `auth:sanctum`.
    *   Wrapped in a custom `AdminCheck` middleware or role check to prevent Clients from hitting the API via Postman/Console.

---

## 🎨 3. Frontend (Vue Page)
*   **File:** `resources/js/pages/admin/members/index.vue`
*   **UI Component:** `VDataTable` from Vuetify.
*   **Features:**
    *   **Server-side ready:** Basic list fetching on `onMounted`.
    *   **Visual Status:** Colored chips for "Active" (success) or "Suspended" (error).
    *   **Action Menu:** Simple toggle button to suspend/activate.
*   **Clean Route:** `/admin/members`.

---

## 🔐 4. ACL Logic (Simplest Standard)
We will follow the `SIMPLE_ACL_GUIDE.md` established previously:

1.  **The Sidebar Link:**
    ```javascript
    {
      title: 'Members',
      to: 'admin-members',
      action: 'manage',
      subject: 'Admin'
    }
    ```
2.  **The Page Protection:**
    ```javascript
    definePage({
      meta: {
        action: 'manage',
        subject: 'Admin',
      },
    })
    ```
3.  **Result:** Clients are automatically blocked from the URL and cannot see the menu. Admins (who have `manage all`) pass through instantly.

---

## 🚀 Workflow
1.  Generate `MemberController` and define API routes.
2.  Create the `admin/members/index.vue` page.
3.  Modify `navigation/vertical/index.js` to include the Admin sections.
4.  Verify that a Client user cannot see or access these new areas.

**Wait for USER approval before proceeding.**
