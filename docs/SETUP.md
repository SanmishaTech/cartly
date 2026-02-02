# Setup & Development

## Prerequisites
- PHP 8.x
- Composer
- MySQL
- Node (for asset builds if needed)

## Environment
Copy .env.example → .env and set DB credentials.

### Email (transactional)
- **SMTP (Brevo):** SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION, SMTP_FROM_ADDRESS, SMTP_FROM_NAME
- **Limits (per shop):** EMAIL_DAILY_LIMIT_PER_SHOP (default 50), EMAIL_MONTHLY_LIMIT_PER_SHOP (optional)
- Sending is provider-agnostic (MailResolver + TransactionalMailService); Brevo is the current transport. Logs: `storage/logs/mail.log`

### India localization defaults:
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
- Migrate: `php cartly migrate`
- Seed demo: `php scripts/seed_db.php` (creates demo shops, root/helpdesk users)

## Customer OAuth (Google/Facebook Login)
To enable shoppers to log in with Google or Facebook on a storefront:

1. Go to Admin Setup → Customer Auth (`/admin/setup/customer-auth`)
2. Check **Enable Google Login** or **Enable Facebook Login**
3. Add the Authorized Redirect URIs shown on the page to your OAuth app:
   - **Google:** [Google Cloud Console](https://console.cloud.google.com) → APIs & Services → Credentials → OAuth client ID (Web application)
   - **Facebook:** [Meta for Developers](https://developers.facebook.com) → App → Facebook Login → Settings
4. Paste the **Client ID** / **Client Secret** (Google) or **App ID** / **App Secret** (Facebook)
5. Save

Use real OAuth credentials from the provider dashboards, not user login credentials. The form disables credential fields when a provider is unchecked to avoid browser autofill.

## Email Setup (Shop Owner)
Admin Setup → Email (`/admin/setup/email`):

1. **Sender:** Choose **Use Cartly Email** (default) or **Send from my domain**. For custom domain, enter From Email, From Name, and Domain; save first.
2. **Reply-To (optional):** Set a common Reply-To address and name for transactional emails; leave empty to use From when available.
3. **Save Email Settings** — saves sender and reply-to only (no nested forms).
4. **Domain verification:** Shown only when “Send from my domain” is selected and domain is filled. Displays Verified/Not verified; **Verify Domain** is a separate action (for now a placeholder; set `domain_verified` in DB to test).
5. **Test email:** Send a test to any address; uses the same sender as saved settings and counts toward the shop’s daily limit.

Per-shop limits are read from .env; exceeding the daily (or optional monthly) limit blocks sending and is logged.

## Dev Server
- Start: php cartly serve
- Alternate: php -S 127.0.0.1:8000 -t public

## Hosts (Local)
- Map cartly.test and demo subdomains
- Use :8000 in URLs when using the dev server; no port when using Caddy (HTTPS on 443)

## Caddy (SSL)

To serve cartly over HTTPS with Caddy:

1. **Prerequisites**
   - Caddy installed and PHP-FPM running (e.g. `php8.4-fpm`).
   - TLS certificate and key that cover **all** your Caddy server names (see **TLS cert that covers cartly.test** below).
   - Hosts: add `127.0.0.1 cartly.test` for the parent site, and `127.0.0.1 demo1.cartly.test`, `127.0.0.1 demo2.cartly.test`, etc. (see `scripts/generate_hosts.sh`).

   **TLS cert that covers cartly.test (mkcert)**  
   Use one multi-SAN cert so both the parent site and shop subdomains work:

   - From the project root, run (adjust names to match your Caddyfile blocks):
     ```bash
     mkcert cartly.test demo1.cartly.test demo2.cartly.test "demo-3.cartly.test"
     ```
   - mkcert creates two files, e.g. `cartly.test+3.pem` and `cartly.test+3-key.pem`. Move them into the project root if they are elsewhere.
   - In the Caddyfile snippet, set `tls` to those paths, e.g.:
     ```
     tls /home/sanjeev/@Learn/cartly/cartly.test+3.pem /home/sanjeev/@Learn/cartly/cartly.test+3-key.pem
     ```
   - Reload Caddy. All server blocks (cartly.test, demo1, demo2, demo-3) will use this cert and browsers will accept it for every name.

2. **Caddyfile**
   - Project root: [Caddyfile](../Caddyfile) at repo root.
   - All shop hosts (demo1, demo2, demo-3) use the **same** document root: the app’s `public` directory. The app resolves the shop from the `Host` header; do not use separate project paths per host.
   - Paths in the Caddyfile are absolute. If your project is not at `/home/sanjeev/@Learn/cartly`, edit the `root *` and `tls` paths to your project path (e.g. `/home/you/cartly/public` and cert paths under `/home/you/cartly/`).

3. **PHP-FPM socket**
   - Snippet uses `unix//run/php/php8.4-fpm.sock`. If your PHP version differs, run `ls /run/php/` and set `php_fastcgi unix//run/php/phpX.Y-fpm.sock` in the Caddyfile snippet.

4. **Run Caddy**
   - From project root: `caddy run --config ./Caddyfile` (foreground), or `caddy reload --config /path/to/cartly/Caddyfile` after editing.
   - Open `https://cartly.test` for the parent site (landing + admin), or `https://demo1.cartly.test` for a shop (no port; Caddy serves 443).
   - **Parent site:** Use the root domain (`cartly.test`). The app shows the landing page at `/` and admin at `/admin` when the request Host equals `APP_DOMAIN`. Ensure `cartly.test` has a Caddy server block and a TLS cert that covers it (e.g. `mkcert cartly.test demo1.cartly.test` for one cert, or separate certs per block).

5. **Troubleshooting**
   - Certificate errors: ensure the `.pem` files exist at the paths in the Caddyfile and are readable by the user running Caddy.
   - 502 Bad Gateway: PHP-FPM not running or wrong socket path; check `systemctl status php8.x-fpm` and `ls /run/php/`.
   - Connection refused: Caddy not listening on 443; run Caddy from the project dir or ensure your service uses this Caddyfile.

## Storage permissions (PHP-FPM / Caddy)

When serving the app with Caddy and PHP-FPM, the PHP process runs as the PHP-FPM user (often `www-data`). The app writes logs, sessions, cache, and uploads under `storage/`. If you see **Permission denied** when opening a log (e.g. `storage/logs/auth-YYYY-MM-DD.log`) or session/cache errors, make `storage` writable by that user.

From the project root (adjust paths if needed):

```bash
# Give the PHP-FPM user ownership of storage (common fix)
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

If your PHP-FPM pool uses a different user (e.g. `nginx`), use that user instead of `www-data`. Check with:

```bash
grep -E '^user|^group' /etc/php/8.4/fpm/pool.d/www.conf
```

After fixing permissions, retry the request (e.g. admin/sudo/3/login).

## Scripts
See OPERATIONS.md for available scripts.
