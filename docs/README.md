# Cartly Documentation

This is the consolidated documentation set for Cartly.

## Quick Links
- Architecture: ARCHITECTURE.md
- Setup & Dev: SETUP.md
- Features: FEATURES.md
- Operations: OPERATIONS.md
- UI & Theme: UI_THEME.md
- Database: DATABASE.md

## Project Summary
Cartly is a multi-tenant e‑commerce SaaS built with Slim + Twig + Eloquent. It includes:
- Landing (public marketing)
- Admin/Root panel (management)
- Storefront (shop domains)

## Quick Start (Dev)
- Start server: php cartly serve
- Migrate DB: php cartly migrate
- Seed demo: php cartly db:seed --demo

## Key Decisions
- Session-based auth (primary)
- Admin login at /admin/login
- CSRF protection enforced for state-changing requests
- India-first defaults (INR, IST, GST, DD‑MM‑YYYY)

## Folder Conventions (Services / Helpers / Factories)
- `src/Services`: classes with side effects or external dependencies (DB, API, filesystem, sessions).
- `src/Helpers`: stateless utilities (pure functions, formatting, URL helpers).
- `src/Factories`: object creation/builders (e.g., logger factories).

## Lazy Helper Getters
Use lazy getters in `AppController` for optional helpers (e.g., `$this->pagination()`, `$this->session()`), so controllers opt in only when needed.

## Pagination Service
Use `PaginationService::paginate()` in list controllers to keep index methods thin and consistent.

## Structured Form Payloads
Use structured POST data for forms (e.g., `shop[...]`, `user[...]`, `subscription[...]`, `payment[...]`, `package[...]`) so controllers can create models with a single array and keep validation keys consistent.

## Admin Form Development Guidelines
Use the shared component classes for consistent spacing and alignment across all admin forms.

### Layout
- Wrap each admin page content in a `card bg-base-100 border border-base-300` with `admin-card-body`.
- Use `admin-form-grid` for two-column sections (auto stacks on mobile).
- Group related fields inside grey cards `card bg-base-200/40 border border-base-300`.
- Use `admin-form-actions` for action button rows.

### Field Controls
- Use `admin-form-control` for every field wrapper (standard gap).
- Inputs: `admin-input`
- Selects: `admin-select`
- Textareas: `admin-textarea`

### Labels and Required Fields
- Always include matching `for` and `id`.
- Use `<span class="label-text font-semibold">Label</span>` for labels.
- Add `<span class="text-error">*</span>` for required fields.
- Include CSRF tokens in every form.

### Tables
- Use `admin-table` for list tables.
- Keep hover on rows (`class="hover"` on `tr`).

### PRG (Post → Redirect → Get)
- All forms must use PRG to avoid browser back-button POST warnings.
- On validation failure: store `errors` + `old` in session flash, then redirect back to the form route.
- On GET: read flash `errors`/`old` and pass to the view.

### Flash Toasts (Admin)
- Use `FlashService::set('success'|'error'|'info'|'warning', message)` after create/update/delete actions.
- Admin layouts render flash toasts globally, so modules only need to set the flash and redirect.

### Validation Guidelines (Valitron)
- Use Valitron in each controller for form validation.
- Keep error keys aligned to form field names (e.g., `errors.email`).
- Add DB-dependent checks (uniqueness/existence) after Valitron validation.

