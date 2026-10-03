# Deploying to cPanel (fidelity-southview.co.zw)

Target: shared cPanel hosting with Apache and PHP 8.3, as shown on the server check page. No Docker, Redis or Node is needed on the server. Assets are built on your machine.

## 1. Build the release on your computer

```bash
cd fspra
./deploy/build-release.sh          # runs npm build + composer --no-dev, writes release/fspra-release.zip
```

## 2. Create the database in cPanel

1. cPanel > MySQL Databases: create database `USER_fspra` and user `USER_fspra` with a long random password.
2. Add the user to the database with ALL PRIVILEGES.
3. Optional hardening after first migrate: in phpMyAdmin revoke UPDATE and DELETE on `ledger_entries` and `audit_logs` for the app user (the app only inserts there; approvals use a separate status update, so if you revoke UPDATE keep it on `ledger_entries.status, approved_by, approved_at` columns only).

## 3. Upload and unpack

1. cPanel > File Manager: upload `fspra-release.zip` to your home directory (`/home/USER/`), **not** inside `public_html`.
2. Extract it. You now have `/home/USER/fspra`.
3. cPanel > Domains > fidelity-southview.co.zw > set **Document Root** to `/home/USER/fspra/public`.
   If your plan does not allow changing the document root, copy `deploy/htaccess-public_html` to `public_html/.htaccess` instead.

## 4. Configure

```bash
cd ~/fspra
cp .env.example .env
nano .env                       # fill DB_*, MAIL_*, keep PAYMENTS_LIVE=false
php artisan key:generate
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # paste as ID_HASH_SALT (never change it later)
php artisan migrate --force
php artisan db:seed --force     # roles, partners, services, pages, schools, house ads (no demo users in production)
php artisan storage:link
php artisan filament:assets
php artisan optimize
php artisan fspra:admin +263771234567 chair@example.com "Chairperson Name"
```

Use cPanel > Terminal (or SSH). If Terminal is disabled, ask the host to enable it once.

## 5. Cron (cPanel > Cron Jobs)

Every minute:

```
* * * * * cd /home/USER/fspra && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

This runs the queue (receipts, notifications), quiet-hour SMS, nightly ledger check and assistant knowledge rebuild.

## 6. HTTPS and security checks

- cPanel > SSL/TLS Status: run AutoSSL so HTTPS is valid for the domain and www.
- The app sends HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy and Permissions-Policy headers, and `public/.htaccess` forces HTTPS, strips X-Powered-By and blocks dot-files.
- In cPanel > MultiPHP INI Editor set `expose_php = Off`, `display_errors = Off`, `upload_max_filesize = 10M`, `post_max_size = 12M`.
- Confirm with https://securityheaders.com and https://www.ssllabs.com/ssltest/ after go-live.

## 7. Google Search

- Robots: production serves `/robots.txt` allowing the public site and disallowing `/app`, `/partner`, `/admin`, `/api`. Non-production environments are fully noindexed.
- Submit `https://fidelity-southview.co.zw/sitemap.xml` in Google Search Console (verify with the DNS TXT record in cPanel > Zone Editor).
- Every public page has a unique title, description, canonical URL, Open Graph image and JSON-LD (Organization, WebSite, BreadcrumbList, NewsArticle, FAQPage, Service, School/Church/LocalBusiness).

## 8. Switching integrations on later

| What | Change in `.env` | Then |
| --- | --- | --- |
| Real SMS | `SMS_DRIVER=http`, `SMS_API_URL`, `SMS_API_KEY` | Adjust `app/Integrations/Sms/HttpSmsGateway.php` payload to the provider |
| Fidelity Life API | `FIDELITY_DRIVER=http`, `FIDELITY_BASE_URL`, `FIDELITY_API_KEY` | Map endpoints in `HttpFidelityClient.php` to Fidelity's spec |
| Bank payments | `TNCB_DRIVER=http`, `TNCB_*`, then `PAYMENTS_LIVE=true` | Give the bank the webhook URL `https://fidelity-southview.co.zw/webhooks/tncb` and the HMAC secret |
| Voice and AI answers | `GEMINI_API_KEY` | Assistant upgrades from help-page search to Gemini answers and voice |

Run `php artisan optimize` after every `.env` change.

## 9. Updating

Build a new zip, upload, extract over `~/fspra` (your `.env` and `storage/` are not in the zip), then:

```bash
php artisan migrate --force && php artisan optimize && php artisan filament:assets
```
