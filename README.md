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

| Area | URL | Demo sign-in (local only) |
| --- | --- | --- |
| Public site | http://127.0.0.1:8000 | none |
| Resident app | /app | any Zimbabwe mobile number; the SMS code is shown on screen locally |
| Partner portal | /partner | 0770000010 / Partner!2026 (Marufu Attorneys), 0770000011 (TN CyberTech Bank), 0770000012 (Fidelity Life), code shown on screen |
| Committee admin | /admin | admin@example.test / ChangeMe!2026 (super admin), treasurer@example.test (finance) |

Demo stands for verification: 1001 to 1200 with any well-formed ID such as 63-123456-A-12.

## Tests

```bash
php artisan test        # uses the fspra_test MySQL database
```

## Switches

- `PAYMENTS_LIVE=false` keeps every bill payment on a "coming soon" screen until the bank gateway is integrated.
- `FIDELITY_DRIVER`, `TNCB_DRIVER`, `SMS_DRIVER`: `fake`/`log` for development, `http` when the partner APIs are ready.
- `GEMINI_API_KEY`: empty means the assistant answers from the help pages only; set it to enable AI answers and voice.

Deployment: see [DEPLOY.md](DEPLOY.md). Build specification: see ../FSPRA-BUILD-SPEC.md.
