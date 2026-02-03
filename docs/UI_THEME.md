# UI & Theme

## Admin UI
- Single shared admin theme
- Sidebar + dashboard layout
- Compact, modern form layout using shared component classes

## Storefront Themes
- Theme directories with metadata
- Active theme set per shop
- Fallback chain: active → default → core views
- Storefront semantics and loading rules are in `docs/STOREFRONT_SEMANTICS.md`.

## Top Bar Component
- Promotional banner displayed at the top of all storefront pages
- Location: `src/Views/themes/{theme}/partials/topbar.twig`
- Included in all theme layouts (`layout.twig`) at the top of `<body>`
- Features:
  - Session-based close state (reappears in new session)
  - HTML message rendering (rich text from TEX editor)
  - Centered message display with responsive design
  - Customizable background and text colors
  - Close button positioned absolutely on the right
- Twig global: `topbar` (array with `enabled`, `message`, `background_color`, `text_color`)
- Session check: `session.topbar_closed` to hide if closed
- Available in all themes (default, classic, modern)

## Default Theme Home Split
- Home page template is `src/Views/themes/default/pages/home.twig`.
- Section rendering is split into partials under `src/Views/themes/default/partials/home/`.
- Sections render in the order defined by `home_sections` and pull text/media from `home_content`.
- Hero uses `partials/home/hero.twig` for the section and `partials/hero/index.twig` for the fallback when the hero section is disabled.
- Testimonials carousel behavior is handled in `partials/home/testimonials.twig` using Alpine and respects reduced motion/mobile.

## Default Theme Assets
- `layout.twig` loads `css/base.css` before `css/style.css` for shared base styles.

## Theme Resolution
- ThemeMiddleware determines admin vs storefront
- Invalid theme falls back to default

## Customization
- DaisyUI theme variables
- Per-shop theme settings stored in DB

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
- Use PRG for all form submissions.
- On validation failure: store `errors` and `old` in session flash and redirect back to the form route.
- On GET: pull flash `errors`/`old` and pass to the view for display.

### Validation Guidelines (Valitron)
- Validate inputs with Valitron inside each controller.
- Keep error keys consistent with form field names to render messages.
- Apply DB-specific checks after Valitron (uniqueness/existence).

