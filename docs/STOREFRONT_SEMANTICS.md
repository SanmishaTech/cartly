# Storefront Semantics & Loading

These guidelines apply to the Default theme storefront views.

## Semantic HTML
- Use semantic layout tags where applicable:
  - `header` for site header.
  - `nav` for primary navigation (desktop and mobile).
  - `main` for page content.
  - `section` for major page blocks.
  - `footer` for site footer.
- Product cards must use `article.product-card`.
- Testimonials must use `blockquote` for the quote and `cite` for the author.
- Testimonials carousel motion is disabled on mobile and when `prefers-reduced-motion` is enabled.

## Image Loading
- Hero images are eager-loaded for LCP.
- All non-hero images must include `loading="lazy"`.

## Typography Scale
- Only the Base slider is exposed in `/admin/setup/themes`.
- Base controls the root size and scales all typography sizes.
- `text-xs/sm/lg/xl` and `text-2xl..text-6xl` are derived from Base using fixed ratios.
