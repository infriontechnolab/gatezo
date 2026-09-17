# Gatezo

Replace the clipboard, the phone calls and the paper sign-in sheet at local events with QR codes.
Print QR codes, stick them on things, and the event runs itself.

**Stack:** Laravel 13 · Filament 5 (organizer panel, Event = tenant) · Blade + Alpine (public pages) ·
hand-written scanner JS (offline-capable, IndexedDB queue) · MySQL 8 · one VPS.

## Who touches what

| Audience | URL | Auth |
|---|---|---|
| Organizer | `/admin` | Filament login, one account can run many events (tenant switcher) |
| Attendee | `/e/{slug}` register · `/pass/{code}` · `/stall/{code}` · `/e/{slug}/feedback` | none |
| Volunteer | `/scan` → 6-digit event code → `/scan/app` | session, no account |

## Local dev

```bash
docker compose up -d           # MySQL 8.4 on :3308
composer install && npm install
cp .env.example .env && php artisan key:generate   # DB_* already point at the docker db
php artisan migrate:fresh --seed
npm run build                  # or `npm run dev` for HMR
php artisan serve
```

Seeded logins: organizer `organizer@gatezo.local` / `password`, event `demo-garba`, volunteer code `123456`.

Test: `php artisan test`. Format: `vendor/bin/pint`.

## How passes work offline

`App\Services\PassToken` puts `EQ1.<code>.<hmac16>` in the QR, signed with a per-event secret.
The scanner downloads a bundle (`/scan/bundle`: secret + pass list + gates) into IndexedDB, verifies
each scan with WebCrypto, queues it, and replays the queue to `/scan/sync` whenever online. Sync is
idempotent on `client_id`; a second scan of the same pass within 10 minutes at any gate is **flagged**
as a duplicate, never blocked. Rotating `event.pass_secret` invalidates every issued pass.

## Layout

```
app/Filament/            panel: resources (gates, attendees, stalls, feedback, scan log), widgets, tenancy pages
app/Http/Controllers/    PublicEventController (attendees), ScannerController (volunteers),
                         VendorController (signed link + leads), PrintController (kit, report, CSV)
app/Services/            PassToken, Qr
resources/views/public   register, pass, stall, feedback
resources/views/scan     join, app (scanner UI; logic in resources/js/scanner.js, modes: gate | lead)
resources/views/print    kit, report (A4 print CSS)
resources/views/vendor   vendor page with lead-capture scanner
public/sw.js             tiny service worker: caches /pass/*, /scan/app, /build/*
infra/                   nginx, supervisor, deploy.sh, SERVER.md
docs/PRD.md              product spec
```

## Feature coverage (the "stick a QR on…" table)

| QR on… | Scanned by | Result | Where |
|---|---|---|---|
| Event poster | Attendee | Register → digital pass | Print kit page 1 → `/e/{slug}` |
| Attendee's pass | Volunteer | Check-in, headcount, re-entry, offline queue | `/scan/app` |
| Gate / zone sign | Volunteer | "On duty here", shows on Who's-where board | Print kit → `/scan/g/{slug}/{code}` |
| Stall card | Attendee | Menu, offers, location; counts scans | Print kit → `/stall/{code}` |
| Attendee's pass | Vendor | Lead captured **only if attendee opted in** on their pass | Vendor signed link (Stalls → Vendor link) |
| Exit card | Attendee | Star rating + comment, anonymous by default | Print kit → `/e/{slug}/feedback` |

Dashboard: capacity gauge, per-gate stats, who's where, arrivals chart, feedback chart.
Print & reports: print kit, post-event report (Print → PDF), attendees CSV; vendors get their own leads CSV.

## Not built yet

- Volunteer shifts UI (table exists, no screens)
- Pass delivery by SMS/WhatsApp API (currently: share button + phone-number lookup)
- Attendee CSV import
