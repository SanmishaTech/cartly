# Database

## Core Tables
- users
- shops
- shop_users (pivot: user_id, shop_id, role) — staff permissions
- shop_customers (pivot: shop_id, user_id, first_seen_at, last_seen_at) — customer activity
- user_oauth_accounts (user_id, provider, provider_user_id, email)
- shop_domains
- packages
- subscriptions
- payments
- seo_metadata
- shop_metadata
- shop_email_settings

## Removed Tables
- plans
- plan_features

## Users (identity only)
- id, email (unique), name (nullable, display name)
- password (nullable for OAuth-only users)
- global_role (enum root|helpdesk, nullable)
- status (active|inactive), created_at, updated_at
- No shop_id, no role on users; shop membership and roles live in shop_users

## shop_users (permissions — staff only)
- user_id, shop_id, role (enum owner|admin|staff)
- Unique (user_id, shop_id); one user can have many shops with a role per shop
- Staff/owners who manage the shop

## shop_customers (customer activity — shoppers)
- shop_id, user_id, first_seen_at, last_seen_at
- Unique (shop_id, user_id)
- Records when logged-in shoppers visit a shop storefront; used for CRM/analytics
- NOT for auth or permissions; distinct from shop_users

## user_oauth_accounts
- user_id, provider (google|facebook), provider_user_id, email
- OAuth identities linked to global user; login can be email/password or linked provider

## Packages Schema (key fields)
- name (string)
- cost_1_month (decimal)
- cost_3_month (decimal)
- cost_6_month (decimal)
- cost_12_month (decimal)
- features (json map; keys defined in PackageConfig, values are numeric limits)
- active (bool)
  
Note: Subscription billing period determines which cost column is used.

## Shops (key fields)
- shop_name (string)
- slug (string, unique)
- status (active|inactive)
- theme (string)
- theme_config (json)
- logo_path (string, nullable)
- favicon_path (string, nullable)
- hero_type (string)
- hero_settings (json)
- sitemap_enabled (bool)

## SEO Metadata (key fields)
- entity_type (shop|product|category|page)
- entity_id (fk id for entity)
- seo_title (string)
- seo_description (string)
- seo_keywords (string)
- canonical_url (string)
- og_title (string)
- og_description (string)
- og_image (string)
- schema_json (json)

## Shop Metadata (key fields)
- shop_id (fk)
- social_media_links (json)
- home_sections (json)
- home_content (json)
- oauth_config (json) — per-shop OAuth for customer login: `{ "google": { "enabled", "client_id", "client_secret" }, "facebook": { "enabled", "app_id", "app_secret" } }`

## Shop Email Settings (key fields)
- shop_id (fk, unique)
- email_mode (global|custom_domain)
- from_name, from_email (nullable; used when custom_domain)
- reply_to_email, reply_to_name (nullable; optional Reply-To for all modes)
- domain (nullable; for custom_domain verification)
- domain_verified (bool, default 0)
- provider (brevo|ses; for future migration)
- daily_email_count, monthly_email_count, last_sent_at (for per-shop limits)
- created_at, updated_at
- No SMTP credentials stored; sending uses app-level SMTP (Brevo) or future SES

## Subscriptions (key fields)
- shop_id (fk)
- package_id (fk, nullable for trials)
- starts_at (datetime)
- expires_at (datetime)
- trial_days (int, nullable)
- type (trial|package)
- renewal_mode (manual|auto)
- payment_method (gateway|cash|bank_transfer|manual)
- price_paid (decimal, nullable)
- currency (string)
- billing_period_months (int)
- next_renewal_at (date, nullable)
  
Note: Each subscription change creates a new row; history is stored in the same table. Expiry is determined at runtime using next_renewal_at.

## Payments (key fields)
- shop_id (fk)
- subscription_id (fk)
- payment_id (string; gateway/cash/bank reference)
- order_id (string)
- amount (decimal)
- currency (string)
- status (pending|captured|refunded|failed)
- method (gateway|cash|bank_transfer|manual)
- paid_at (datetime)

## Migrations
- Follow documented order in migrations folder

## Future Tables (planned)
- invoices
- audit_logs
- support/helpdesk
- feature_usage
