# Setup & Development

## Prerequisites
- PHP 8.x
- Composer
- MySQL
- Node (for asset builds if needed)

## Environment
Copy .env.example → .env and set DB credentials.

India localization defaults:
- APP_TIMEZONE=Asia/Kolkata
- APP_LOCALE=en_IN
- APP_COUNTRY=India
- CURRENCY=INR
- CURRENCY_SYMBOL=₹
- GST_RATE=18
- GST_ENABLED=true
- DATE_FORMAT=d-m-Y
- DATETIME_FORMAT=d-m-Y H:i:s
- TIME_FORMAT=H:i:s

## Database
- Migrate: php cartly migrate
- Seed dev: php cartly db:seed
- Seed demo: php cartly db:seed --demo

## Dev Server
- Start: php cartly serve
- Alternate: php -S 127.0.0.1:8000 -t public

## Hosts (Local)
- Map cartly.test and demo subdomains
- Use :8000 in URLs

## Scripts
See OPERATIONS.md for available scripts.
