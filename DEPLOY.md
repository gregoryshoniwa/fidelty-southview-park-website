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
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # paste as ID_HASH_SALT (never change it later; the app refuses to start without it)
php artisan migrate --force
php artisan db:seed --force     # roles, partners, services, pages, schools, house ads (no demo users in production)
php artisan storage:link
php artisan filament:assets
php artisan optimize
php artisan fspra:admin +263771234567 chair@example.com "Chairperson Name"
```

Use cPanel > Terminal (or SSH). If Terminal is disabled, ask the host to enable it once.

### Before go-live (security review sign-off)

- **SMS provider**: set `SMS_DRIVER=http` with your Econet/NetOne bulk SMS account. Without it nobody can receive sign-in codes. `SMS_DAILY_CAP` stops SMS-pumping fraud.
- **Committee sign-in**: committee members sign in at `/admin` with email, password and an authenticator app (two-factor is mandatory; they are asked to set it up on first sign-in). Committee and partner phone numbers cannot use the resident SMS sign-in.
- **Stand verification**: `FIDELITY_DRIVER=manual` until Fidelity Life's API is connected. Residents submit ID and stand; the committee checks Fidelity's records and clicks **Confirm stand** in Admin > Community > Residents.
- **Payments**: keep `PAYMENTS_LIVE=false`. When the bank is ready set `TNCB_DRIVER=http`, a random `TNCB_WEBHOOK_SECRET` of at least 32 characters, then `PAYMENTS_LIVE=true`. Webhooks are verified by HMAC and then confirmed with the bank before anything is released.
- **Proxies**: leave `TRUSTED_PROXIES` empty unless you put Cloudflare in front (then list Cloudflare's IP ranges).

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
- Submit `https://fidelity-southview.co.zw/sitemap.xml` in Google Search Console (verify with a DNS TXT record in Cloudflare, which hosts this domain's DNS).
- Every public page has a unique title, description, canonical URL, Open Graph image and JSON-LD (Organization, WebSite, BreadcrumbList, NewsArticle, FAQPage, Service, School/Church/LocalBusiness).

## 8. Switching integrations on later

| What | Change in `.env` | Then |
| --- | --- | --- |
| Real SMS | `SMS_DRIVER=http`, `SMS_API_URL`, `SMS_API_KEY` | Adjust `app/Integrations/Sms/HttpSmsGateway.php` payload to the provider |
| Fidelity Life API | `FIDELITY_DRIVER=http`, `FIDELITY_BASE_URL`, `FIDELITY_API_KEY` | Map endpoints in `HttpFidelityClient.php` to Fidelity's spec |
| Bank payments | `TNCB_DRIVER=http`, `TNCB_*`, then `PAYMENTS_LIVE=true` | Give the bank the webhook URL `https://fidelity-southview.co.zw/webhooks/tncb` and the HMAC secret |
| Voice and AI answers | `GEMINI_API_KEY` | Assistant upgrades from help-page search to Gemini answers and voice |

Run `php artisan optimize` after every `.env` change.

## 9. Sign-in: Google directly, SMS via Firebase, email via your own mailbox

### Continue with Google (no Firebase)

1. https://console.cloud.google.com, select project **fidelity-southview-park** (or any project you own).
2. **Google Auth Platform > Branding** (first time it asks you to set it up): app name "Fidelity Southview Park Residents Association", support email, logo `deploy/google-consent-logo-120.png`, home `https://fidelity-southview.co.zw`, privacy `https://fidelity-southview.co.zw/privacy`, terms `https://fidelity-southview.co.zw/terms`, authorised domain `fidelity-southview.co.zw`. **Audience**: External.
3. **APIs & Services > Credentials > Create credentials > OAuth client ID**, type **Web application**, name "Southview web". Authorised redirect URIs:
   - `http://localhost:8000/auth/google/callback` (your computer; open the app at `localhost`, not `127.0.0.1`)
   - `https://fidelity-southview.co.zw/auth/google/callback`
4. Copy the Client ID and Client secret into `.env` as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`, then `php artisan optimize`. The **Continue with Google** button appears only once both are set.
5. Once the site is live: verify the domain in Search Console (DNS TXT record at Cloudflare, or the HTML file method through cPanel), then **Branding > Submit for verification** so Google shows the logo.

Only the scopes `openid email profile` are requested, so Google does not need a security review. Committee and partner accounts can never sign in with Google.

### Phone codes via Firebase

1. https://console.firebase.google.com, project **fidelity-southview-park**, **Build > Authentication > Sign-in method**: enable **Phone**.
2. **Authentication > Settings > SMS region policy**: Allow, **Zimbabwe** only.
3. **Authentication > Settings > Authorized domains**: add `fidelity-southview.co.zw`.
4. Copy the web app's `apiKey`, `authDomain`, `projectId`, `appId` into `.env` as `FIREBASE_API_KEY`, `FIREBASE_AUTH_DOMAIN`, `FIREBASE_PROJECT_ID`, `FIREBASE_APP_ID`, and set `AUTH_PHONE_PROVIDER=firebase`. Use `AUTH_PHONE_PROVIDER=local` to send codes through your own SMS gateway instead.
5. Zimbabwe SMS cost about US$0.09 each; the first 10 per day are free. Set a budget alert in Google Cloud Billing. For testing, add your number under **Phone > Phone numbers for testing** with a fixed code.

The Firebase web keys are public by design; security comes from the server checking every Firebase token's Google signature, project and freshness. Committee and partner accounts can never sign in through Firebase.

### Branded emails

Sign-in links and resident notifications are sent from `info@fidelity-southview.co.zw` through the cPanel mail server, using the branded template in `resources/views/emails`. Put that mailbox's password in `MAIL_PASSWORD`. The domain already publishes SPF, DKIM and DMARC for this server, so no DNS changes are needed.

### Firebase email domain (not needed)

Only needed if you ever send email through Firebase again. DNS is hosted at Cloudflare, managed by WebDev:

The domain's DNS is hosted at **Cloudflare** (nameservers cory/perla.ns.cloudflare.com), so add these in Cloudflare > fidelity-southview.co.zw > **DNS > Records**, not in cPanel's Zone Editor:

| Type | Name | Content | Proxy |
| --- | --- | --- | --- |
| TXT | `@` | **edit the existing SPF record** to `v=spf1 +a +mx +ip4:156.38.135.148 include:_spf.firebasemail.com -all` | n/a |
| TXT | `@` | `firebase=fidelity-southview-park` | n/a |
| CNAME | `firebase1._domainkey` | `mail-fidelity--southview-co-zw.dkim1._domainkey.firebasemail.com` | DNS only (grey cloud) |
| CNAME | `firebase2._domainkey` | `mail-fidelity--southview-co-zw.dkim2._domainkey.firebasemail.com` | DNS only (grey cloud) |

A domain may have only one SPF record, so merge Firebase into the existing one rather than adding a second. Then click **Verify** in Firebase; Cloudflare changes usually show within minutes.

### Cloudflare proxy

The website is served through Cloudflare's proxy, so `.env` must set `TRUSTED_PROXIES` to Cloudflare's ranges (already filled in `.env.example`). Without it every visitor looks like a Cloudflare IP and rate limits would block real residents together. Set Cloudflare SSL/TLS mode to **Full (strict)** once AutoSSL has issued the cPanel certificate.

### Phone numbers without paid SMS (`AUTH_PHONE_PROVIDER=none`)

With `none`, no SMS is sent. Residents prove their number on WhatsApp, or type it and the committee confirms it.

**Committee confirmation (always available):** a typed number shows as "(unconfirmed)" in Admin > Community > Residents. Click **Confirm phone** after checking it (call it, or match Fidelity Life's records). **Confirm stand** also confirms the number. Unconfirmed numbers are never used to sign in or to send messages.

**WhatsApp verification (free):** the resident sends `VERIFY 123456` from their WhatsApp to the association's number; Meta tells the site which number sent it. Receiving messages is free on the WhatsApp Cloud API.

1. https://developers.facebook.com > **My Apps > Create app**, use case **Connect with customers through WhatsApp**, and create (or pick) a free business portfolio called "Fidelity Southview Park Residents Association".
2. **WhatsApp > API Setup > Add phone number**: enter the association's line and confirm it by SMS or voice call. Display name: "Fidelity Southview Park Residents Association" (Meta reviews it).
   A number on the Cloud API cannot normally stay in the ordinary WhatsApp app. If the line is in the **WhatsApp Business app**, Meta may offer to keep both running ("coexistence"); otherwise remove WhatsApp from that phone first, after backing up chats.
3. **WhatsApp > Configuration > Webhook**: Callback URL `https://fidelity-southview.co.zw/webhooks/whatsapp`, Verify token = any long random text (also put it in `WHATSAPP_VERIFY_TOKEN`). Click **Verify and save**, then **Manage > subscribe to `messages`**. The site must be live for this step.
4. **App settings > Basic**: copy **App secret** into `WHATSAPP_APP_SECRET`; add the privacy URL `https://fidelity-southview.co.zw/privacy`; switch the app to **Live**.
5. `.env`: `WHATSAPP_NUMBER=2637XXXXXXXX` (the line, digits only), then `php artisan optimize`. The **Continue with WhatsApp** and **Verify with WhatsApp** buttons appear.
6. Optional thank-you reply ("Your number is confirmed"): **Business settings > System users**, create one, generate a permanent token with `whatsapp_business_messaging`, and set `WHATSAPP_TOKEN` and `WHATSAPP_PHONE_NUMBER_ID` (shown under API Setup). Replies to a message the resident sent are free.

**Testing on your computer** (Meta cannot reach localhost): set the three `WHATSAPP_*` values to anything, press **Get my code**, then run
`php artisan fspra:whatsapp-test 123456 0771234567` with the code shown. The page continues by itself.

## 10. Updating

Build a new zip, upload, extract over `~/fspra` (your `.env` and `storage/` are not in the zip), then:

```bash
php artisan migrate --force && php artisan optimize && php artisan filament:assets
```
