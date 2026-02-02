# Operations

## Scripts
Located in /scripts:
- start.sh
- generate_hosts.sh
- migrate.php
- seed_db.php — demo data (packages, shops, root/helpdesk users); run via `php cartly db:seed` or `php scripts/seed_db.php`
- test_api.sh
- test_api_complete.sh
- test_login.php
- test_logging.php
- test_validation.php
- test_package_setup.sh
- test_packages.php

## Logging
Monolog channels:
- app, payment, order, auth, api, error, database, security
Transactional email (send/failure/limit): `storage/logs/mail.log` (plain append; see EMAIL.md).
Logs are stored in storage/logs with rotation.

## Maintenance
- Clear cache: storage/cache
- Review sessions: storage/sessions

## Troubleshooting
- Ensure :8000 in local URLs
- Verify .env DB settings
- Check storage/logs for runtime errors
