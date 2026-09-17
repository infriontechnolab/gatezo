# Gatezo

Replace the clipboard, the phone calls and the paper sign-in sheet at local events with QR codes.
Print QR codes, stick them on things, and the event runs itself.

**Stack:** Laravel 13 · Filament 5 (organizer panel, Event = tenant) · Blade + Alpine (public pages) ·
hand-written scanner JS (offline-capable, IndexedDB queue) · MySQL 8 · one VPS.

## Who touches what

| Audience | URL | Auth |
|---|---|---|
| Visitor | `/` landing page (real QR codes to the demo event, real screenshots) | none |
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

**Demo event for showing prospects** (1,500 registrations, 1,100 arrivals on a garba curve, re-entries, stalls with
leads, roster + duty logs, 100 feedback responses, one finished lucky draw with proof and one ready to run):

```bash
php artisan db:seed --class=DemoSeeder   # ~12s, re-runnable (replaces the previous demo event)
```
Login `demo@gatezo.local` / `password`, event `sharad-utsav`, volunteer code `246810`. The event is dated tonight,
or yesterday if you run it before the evening, so live widgets always have data.

Test: `php artisan test`. Format: `vendor/bin/pint`.

## Volunteer access control

Joining needs the event's 6-digit code (30 attempts/min per IP; 10 wrong codes → 15-minute block, every
attempt logged with device + IP). The bundle a phone downloads carries a per-pass signature, never the
event secret, so a leaked bundle cannot forge passes. Organizers can: issue a **new code** (all sessions
end), **remove** a volunteer (session ends, cannot rejoin), turn on **roster-only** (only names on the
Shifts list can join) and **approval** (new joiners wait on a holding screen until approved; no scans or
draw claims until then). Panel → People & gates → Volunteers.

## How passes work offline

`App\Services\PassToken` puts `EQ1.<code>.<hmac16>` in the QR, signed with a per-event secret.
The scanner downloads a bundle (`/scan/bundle`: pass list with per-pass signatures + gates) into IndexedDB
(memory fallback), verifies each scan by comparing signatures, queues it, and replays the queue to `/scan/sync` whenever online. Sync is
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
public/sw.js             tiny service worker: caches /pass/*, /scan/app, /build/*, icons
/manifest.webmanifest    dynamic (ManifestController): pass pages install as "<event> pass", /scan as "Gatezo scanner"
public/icons/            PNG icons 192/512 any + maskable, apple-touch-icon (generated from the G mark)
public/brand/            logo-light.png (coral), logo-dark.png (white, for plum), mark.png (the G), logo-original.png
infra/                   nginx, supervisor, deploy.sh, SERVER.md
docs/PRD.md              product spec
docs/FLOWS.md            flow diagrams (system map, journeys, event day, data model) + PNGs in docs/flows/
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

Organizer tools: CSV import (loose headers, dedupe by phone), revoke/restore pass, invalidate all passes,
vendor link regenerate, Team page (invite co-organizer via set-password link, no email needed), duplicate event.

Shifts: roster by name (links to the volunteer when they join), status Upcoming / Starting / On duty /
At another post / Late / Missed / Done, gate preselect + banner in the scanner, "Not arrived" on the board,
roster in the post-event report.
Lucky draw: pool = inside now / checked in / registered (+ filters), prizes in order, winner + backups drawn
upfront from a seed whose hash is committed at create and revealed at finish; Stage page (Run → Announce next →
Claimed / Forfeit), signed presenter screen with rolling names + countdown, pass banner for the winner, volunteer
claim by scanning the pass, public results page with proof. Design notes in docs/DRAW-RD.md.
Gate board: signed public link (Print & reports) to a full-screen "inside now" page for a tablet at the entrance.

## Not built yet

- Pass delivery by SMS/WhatsApp API (currently: share button + phone-number lookup)
- SMTP config (password reset currently logs to file)
