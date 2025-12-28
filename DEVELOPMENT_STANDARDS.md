# Development Standards - ClubMaster Portal

This document outlines the mandatory coding practices and architectural standards for the ClubMaster project to ensure long-term performance, scalability, and maintainability.

---

## 🚀 Database Performance (Eager Loading)

**Standard:** All database queries involving relationships must use **Laravel Eager Loading**.

**Requirement:** 
*   Always use the `with(['relationship'])` method when fetching data that relies on related models.
*   **Example:** Use `User::with(['subscription.plan'])` instead of calling `$user->subscription->plan->name` inside a loop.
*   **Rationale:** This prevents the **N+1 query problem**, where the application makes dozens or hundreds of unnecessary database calls, drastically slowing down the portal as the user base grows.
*   **Strict Rule:** Never use flat-table shortcuts or lazy-loading for core business logic or API endpoints.

---

## 🏗️ Architecture

*   **Headless First:** Laravel acts strictly as a secure API. Vue 3 acts as the interactive engine.
*   **Vuexy Standard:** Adhere to the established Vuexy/Vuetify patterns for UI components to maintain visual consistency.

---

## 🔐 Security

*   **Sanctum Protection:** All business and admin routes must be protected by the `auth:sanctum` middleware.
*   **ACL Consistency:** Ensure CASL subjects on the frontend match the logic returned by the `AuthController`.
