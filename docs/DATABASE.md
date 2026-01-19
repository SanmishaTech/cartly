# Database

## Core Tables
- shops
- shop_domains
- packages
- subscriptions
- payments

## Removed Tables
- plans
- plan_features

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
- name (string)
- slug (string, unique)
- status (active|inactive)
- theme (string)
- theme_settings (json)

## Subscriptions (key fields)
- shop_id (fk)
- package_id (fk, nullable for trials)
- starts_at (datetime)
- expires_at (datetime)
- trial_days (int, nullable)
- status (active|trial|expired|suspended|pending)
- renewal_mode (manual|auto)
- payment_method (gateway|cash|bank_transfer|manual)
- price_paid (decimal, nullable)
- currency (string)
- billing_period_months (int)
- next_renewal_at (date, nullable)
  
Note: Each subscription change creates a new row; history is stored in the same table.

## Payments (key fields)
- shop_id (fk)
- subscription_id (fk)
- payment_id (string; gateway/cash/bank reference)
- order_id (string)
- amount (decimal)
- currency (string)
- status (captured|refunded|failed)
- method (gateway|cash|bank_transfer|manual)
- paid_at (datetime)

## Migrations
- Follow documented order in migrations folder

## Future Tables (planned)
- invoices
- audit_logs
- support/helpdesk
- feature_usage
