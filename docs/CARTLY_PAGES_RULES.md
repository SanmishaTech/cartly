# Cartly Pages Rules

## Page Types
- Cartly supports only TWO page types:
  1. Standard Page (user-created)
  2. System Page (predefined, restricted)
- Do NOT add more page types.

## Pages Table Responsibility
- Pages table handles ONLY:
  - routing
  - content
  - menu visibility
- Pages table must NOT store any SEO fields.

## Content Storage
- Page content must be stored as structured `content_json`
- Raw HTML/text storage is NOT allowed
- Pages reuse the same block/section system as Home

## SEO Handling
- All SEO data including schema_json MUST be stored in `seo_metadata`
- SEO is resolved using (entity_type = 'page', entity_id)

## Menu Handling
- Header and Footer links must be menu-driven
- Footer has two locations:
  - footer_quick
  - footer_customer
- Pages can be assigned to menus, not hard-coded

## Rendering Rules
- Only published pages are publicly accessible
- Slug must be unique per shop
- SEO + schema are injected from seo_metadata at render time
