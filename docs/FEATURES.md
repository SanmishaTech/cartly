# Features

## Package Module (Core)
- CRUD for packages
- Fields: name, cost_1_month, cost_3_month, cost_6_month, cost_12_month, features (JSON map), active
- Each package has fixed 1/3/6/12 month costs in a single row; features are shared across periods
- Billing period selection in subscriptions maps to the corresponding package cost column
- Validation uses Valitron
- Routes: /admin/packages
- Features are fixed by config (see PackageConfig)
- List supports search, sort, and pagination
- CRUD flow (admin):
  - List: GET /admin/packages
  - Create: GET /admin/packages/create → POST /admin/packages/store
  - Edit: GET /admin/packages/{id}/edit → POST /admin/packages/{id}/update
  - Delete: POST /admin/packages/{id}/delete
- Security:
  - Permission gated by packages.manage
  - CSRF token required on all POST actions

## Localization (India)
- Currency, GST, date/time formats from .env
- Twig filters: currency, local_date, local_datetime, local_time

## Authentication
- Session-based auth (primary)
- Role-based access model (root, helpdesk, shop_owner, operations, shopper)
- Admin login: /admin/login — email/password only
- Storefront customer login: email/password or OAuth (Google/Facebook)
- OAuth (Google/Facebook) for shoppers only; admin-side always email/password
- Customer OAuth configured per shop in Admin Setup → Customer Auth (`shop_metadata.oauth_config`)
- shop_customers table records when logged-in shoppers visit a storefront (first_seen_at, last_seen_at) for CRM/analytics

## Landing Page
- Marketing content, pricing, FAQ

## Admin UI
- Shared admin theme across root/shop admin

## Shops & Subscriptions (Root)
- Root creates shop + shop owner user + subscription in one flow
- Root selects Paid Package or Trial (7/10/15 days)
- Trial uses `trial_days` on subscription; no payment required
- Paid subscription requires payment details; amount is derived from the package + period
- Root can disable a shop even if subscription is active
  
## Subscription History
- Every change creates a new subscription row
- Latest row is the current subscription
- History is shown on the Manage Subscription screen
