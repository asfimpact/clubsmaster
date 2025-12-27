# Clubsmaster Project Context

## Project Vision
- **Phase 1:** Subscription Gateway (WordPress -> app.clubsmaster.com).
- **Core Function:** Handle Login/Register and Plan Purchase.
- **Roles:** Admin (Full management) and User (Profile/Subscription only).

## Tech Stack
- **Frameworks:** Laravel 12 + Vue 3 (Composition API) + Vite.
- **UI Template:** Vuexy Full Version.
- **Database:** MySQL (XAMPP).

## Coding Rules (Strict)
1. **No Over-Engineering:** Use built-in Vuexy features. Do not create new routing systems.
2. **Auth Templates:** Always use the V1 templates located in `resources/js/views/pages/authentication/`.
3. **Layouts:** Use `meta: { layout: 'blank' }` for all auth pages.
4. **Sidebar:** Control visibility via `action` and `subject` in `navigation/vertical/index.js`.
5. **Workflow:** Always create an implementation plan for me to review before writing any code.
