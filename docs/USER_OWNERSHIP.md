# User Ownership & Authorization

## Core Invariants

1. **users = identity** — The `users` table holds only global identity (email, name, password, status). No shop, no shop role, no business data. One row per human; email unique globally.
2. **shop_users = permissions (staff only)** — Who can do what in a shop lives in `shop_users` (user_id, shop_id, role). Shop-scoped roles are owner | admin | staff. Same user can have different roles in different shops.
3. **shop_customers ≠ shop_users** — `shop_customers` tracks shoppers who visit a storefront (first_seen_at, last_seen_at). Used for CRM/analytics. Staff permissions in `shop_users`; customer activity in `shop_customers`. Tables are kept separate.
4. **global_role ≠ shop role** — `users.global_role` is platform-level only (root | helpdesk | NULL). It is not a shop role. Shop roles exist only in `shop_users`.
5. **Guests never exist in DB** — Anonymous visitors are session-only. Never insert a user row for a guest.
6. **Everything business-related belongs to a shop** — Products, orders, staff, menus, pages, subscriptions, payments, etc. must have a `shop_id` FK. New business tables must include `shop_id` and a FK to `shops`.

## Identity
- Email is the primary user identity; one email = one user platform-wide.
- Guests are never stored; use session only.
- Password can be null for OAuth-only users (e.g. shoppers who only use Google/Facebook). Admin users use email/password.

## Global roles (users.global_role)
- **root:** Full platform admin; can manage all shops, packages, users, subscriptions; no shop membership required.
- **helpdesk:** Support; can sudo into shop owners; no shop membership required.
- **NULL:** No global admin role; shop access only via `shop_users` or as shopper.

## Shop roles (shop_users.role)
- **owner:** Full shop management (settings, staff, products, orders, setup). A shop can have multiple owners.
- **admin:** High access within the shop; scope defined by permission map.
- **staff:** Limited scope (e.g. orders, inventory); still shop-scoped.

## Ownership
- No `shops.owner_id` column. Ownership = presence in `shop_users` with role = owner for that shop.
- All shop-scoped entities have `shop_id`; access = `$user->canManageShop($shopId)`.

## OAuth
- OAuth identities live in `user_oauth_accounts`; link to `users` by `user_id`. Login can be email/password or any linked provider. Admin-side uses email/password; shoppers can use OAuth.
- Customer OAuth (Google/Facebook) is configured per shop in `shop_metadata.oauth_config`. Credentials must be real OAuth app credentials (Client ID/Secret from Google Cloud Console, App ID/Secret from Meta), not user login credentials. Form fields use `autocomplete="off"` and `disabled` when provider is unchecked to avoid browser autofill.

## Sessions
- Server-side sessions only; no JWT for web.
- Session stores: user_id, user_email, user_name (display; may be email), user_role (effective role for current shop), shop_id (current shop context).

## Future Modules Must
- Add `shop_id` to new shop-scoped tables.
- Check `$user->canManageShop($shopId)` before any write to shop-scoped resources.
- Never create user rows for guests.
- Use effective role (from `getEffectiveRoleForShop($user, $shopId)`) when calling `AuthorizationService::roleHasPermission($role, $permission)`.
