# Email System (Design & Implementation)

## Overview
Transactional email for Cartly: provider-agnostic (Brevo now, SES-ready). No shop SMTP; no newsletter/bulk.

## Modes
- **Global (default):** From = "&lt;Shop Name&gt; via Cartly &lt;no-reply@…&gt;" (app SMTP From).
- **Custom domain:** From = shop `from_email` / `from_name` when `email_mode=custom_domain` and `from_email` set. Reply-To uses `reply_to_email` or falls back to `from_email`.

## Database
Table `shop_email_settings`: shop_id (unique), email_mode (global|custom_domain), from_name, from_email, reply_to_email, reply_to_name, domain, domain_verified, provider (brevo|ses), daily_email_count, monthly_email_count, last_sent_at, timestamps. No SMTP credentials stored.

## Config (.env)
- **Brevo:** SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION, SMTP_FROM_ADDRESS, SMTP_FROM_NAME.
- **Limits:** EMAIL_DAILY_LIMIT_PER_SHOP (default 50), EMAIL_MONTHLY_LIMIT_PER_SHOP (optional). Read at runtime; no hardcoded limits.

## Services
- **MailResolver:** Resolves from_email, from_name, reply_to_email, reply_to_name for a shop (or app default when shop null).
- **TransactionalMailService:** send(?Shop, to, toName, subject, html, text). Resolves envelope via MailResolver, enforces per-shop limits, sends via MailService (Brevo), updates counts, logs to `storage/logs/mail.log`.
- **MailService:** Low-level send/sendWithEnvelope; X-Mailer header suppressed (whitespace).

## Admin UI
- **Route:** `/admin/setup/email`.
- **Flow:** One main form (Sender + Reply-To + Save Email Settings). After save, separate block: Domain verification (status + Verify Domain form, shown when custom_domain and domain filled). Then Test email form. No nested forms.
- **Verify Domain:** Placeholder (sets domain_verified=0, shows info message). Manual DB set of domain_verified for testing.
- **UX:** Do not mention Brevo or SES. Show "Emails are sent using Cartly email until your domain is verified" where appropriate.

## Limits & logging
- Per-shop daily (and optional monthly) limit from .env; block send when exceeded; log sent, failure, limit_exceeded.
