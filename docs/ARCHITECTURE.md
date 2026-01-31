# Architecture

## Layers
1) Landing (public marketing)
2) Admin/Root (management, authenticated)
3) Storefront (shop domain + theme)

## Request Flow
1) Domain/Shop resolver
2) Theme middleware (admin vs storefront)
3) Controller
4) Twig rendering

## Routing
- Admin: /admin/*
- Admin login: /admin/login
- Storefront: domain-based routing

## Auth & Roles
- Session-based auth is the primary model
- **Global roles** (users.global_role): root, helpdesk — platform-level only; no shop membership required
- **Shop roles** (shop_users.role): owner, admin, staff — one user can have different roles in different shops
- Root can manage all shops; helpdesk can sudo into shop owners (users with shop_users.role = owner)
- users = identity only (email, name, password, status); shop_users = staff permissions (user_id, shop_id, role)
- shop_customers = shopper activity per shop (first_seen_at, last_seen_at); distinct from shop_users
- OAuth (Google/Facebook) lives in user_oauth_accounts; admin-side uses email/password; shoppers can use OAuth
- Authorization is enforced via permission middleware (AuthorizationService); effective role = getEffectiveRoleForShop(user, shop_id)
- CSRF middleware validates tokens on POST/PUT/PATCH/DELETE

## Middleware Order (Slim — last added runs first)
CsrfMiddleware → AuthMiddleware → ShopResolverMiddleware → ShopCustomerMiddleware → SubscriptionEnforcerMiddleware → ThemeMiddleware → Routes. ShopResolver must run before ShopCustomerMiddleware so the shop is set on the request when recording shop_customers.

## Theme Resolution
- Storefront themes per shop
- Admin UI is a single shared theme
- Fallback order: active theme → default theme → core views

## Key Constraints
- Dev URLs require :8000
- Localization defaults to India
