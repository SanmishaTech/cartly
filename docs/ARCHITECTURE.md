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
- Roles: root, helpdesk, admin, operations, shopper
- Root can manage all shops; helpdesk can sudo
- Authorization is enforced via permission middleware (AuthorizationService)
- CSRF middleware validates tokens on POST/PUT/PATCH/DELETE

## Theme Resolution
- Storefront themes per shop
- Admin UI is a single shared theme
- Fallback order: active theme → default theme → core views

## Key Constraints
- Dev URLs require :8000
- Localization defaults to India
