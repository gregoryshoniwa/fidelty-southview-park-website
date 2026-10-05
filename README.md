# Fidelity Southview Park Residents Association platform

Public website, resident app, partner portals and committee admin for the Fidelity Southview Park Residents Association, Amalinda, Harare. Domain: fidelity-southview.co.zw.

## Stack

Laravel 13 on PHP 8.3 · MySQL 8 · Vue 3 (resident app and partner portal) · Blade + Alpine CSP build (public site, SEO-first) · Tailwind CSS 4 · Filament 5 (committee admin at `/admin`) · Lucide icons · Reka UI dialogs · Sonner toasts · Gemini Live API (assistant voice) · TN CyberTech Bank gateway (adapter, payments switched off until integrated).

## Run locally

```bash
composer install && npm install
cp .env.example .env   # set DB_* (local MySQL), then:
php artisan key:generate
php artisan migrate:fresh --seed     # includes demo accounts in local only
npm run dev & php artisan serve
```

## Local demo accounts

These exist only on a local copy seeded with `php artisan migrate:fresh --seed`. They are never created in production.

| Where | Sign in with | Password |
| --- | --- | --- |
| Committee admin, `/admin` (full access) | `admin@example.test` | `ChangeMe!2026` |
| Committee admin, `/admin` (finance only) | `treasurer@example.test` | `ChangeMe!2026` |
| Partner portal, `/partner` (Marufu Attorneys) | phone `0770000010` | `Partner!2026` |
| Partner portal, `/partner` (TN CyberTech Bank) | phone `0770000011` | `Partner!2026` |
| Partner portal, `/partner` (Fidelity Life) | phone `0770000012` | `Partner!2026` |
| Resident app, `/app` | any Zimbabwean mobile, e.g. `0771234567` | none (SMS code) |

- **SMS codes:** for partners and residents, the code is shown on screen locally because no real SMS is sent.
- **Demo resident:** `0771234567` is already verified on stand 1001.
- **Admin two-factor:** the first admin sign-in shows a QR code. Scan it with Google Authenticator or Microsoft Authenticator, enter the 6-digit code, and keep the recovery codes.
- **Verifying a test stand:** use any stand from 1001 to 1200 with an ID like `63-123456-A-12`.
- **Production:** create the real committee admin with `php artisan fspra:admin` (see [DEPLOY.md](DEPLOY.md)).

## Tests

```bash
php artisan test        # uses the fspra_test MySQL database
```

## Switches

- `PAYMENTS_LIVE=false` keeps every bill payment on a "coming soon" screen until the bank gateway is integrated.
- `FIDELITY_DRIVER`, `TNCB_DRIVER`, `SMS_DRIVER`: `fake`/`log` for development, `http` when the partner APIs are ready.
- `GEMINI_API_KEY`: empty means the assistant answers from the help pages only; set it to enable AI answers and voice.

Deployment: see [DEPLOY.md](DEPLOY.md). Build specification: see ../FSPRA-BUILD-SPEC.md.
