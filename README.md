# Gatezo

Replace the clipboard, the phone calls and the paper sign-in sheet at local events with QR codes.
Print QR codes, stick them on things, and the event runs itself.

**Stack:** Laravel 13 · Filament 5 (organizer panel, Event = tenant) · Blade + Alpine (public pages) ·
hand-written scanner JS (offline-capable, IndexedDB queue) · MySQL 8 · one VPS.

## Who touches what

| Audience | URL | Auth |
|---|---|---|
| Visitor | `/` landing page (real QR codes to the demo event, real screenshots) | none |
| Organizer | `/admin` (`/admin/register` to sign up) | Filament login, one account can run many events (tenant switcher) |
| Attendee | `/e/{slug}` register · `/pass/{code}` · `/stall/{code}` · `/e/{slug}/feedback` | none |
| Volunteer | `/scan` → 6-digit event code → `/scan/app` | session, no account |
| Gatezo staff | `/ops` | Filament login, `users.is_admin` only (`php artisan gatezo:admin you@example.com`) |
| Prospect | `/demo` signs into the seeded demo event (needs `GATEZO_DEMO=true`, resets nightly via `gatezo:demo-reset`) | shared account |

## Sign-up and plans

"Start your event" on the landing page goes to `/admin/register`: name, email, WhatsApp number, password. The new
account lands on the **free plan** and is sent straight to "Create event". Every sign-up is a lead: set
`GATEZO_SIGNUP_NOTIFY=you@example.com` to get a one-line mail per sign-up with a wa.me link to the organizer.

Plans are caps, not clocks (`config/gatezo.php` → `plans`), so an organizer who signs up two months before the event
is not cut off before their first scan:

| | Free | Pro |
|---|---|---|
| Events created | 1 | unlimited |
| Registrations per event (form, CSV, manual) | 200 | unlimited |
| Organizers per event | 1 | unlimited |
| Scanner, stalls, feedback, draw, reports | all | all |

An event is governed by the plan of the user who **created** it; being invited to someone's event uses none of your
own allowance. Free-plan owners see a usage strip under the topbar with an "Upgrade to Pro" WhatsApp link (also in
the user menu); when the cap bites, the public form says "Registration is full", CSV import stops and says so, the
Team page swaps *Invite* for *Upgrade*, and "Create event" / "Duplicate event" disappear. Upgrade by hand after the chat:

```bash
php artisan gatezo:plan bhavesh@example.com pro     # no plan argument just shows where they stand
```

**Ops panel** (`/ops`, staff only): dashboard (sign-ups this week/month, free vs Pro, upcoming and live events,
registrations), **Organizers** (plan switch, WhatsApp link, set-password link, **Log in as** → opens their panel with a
"Back to Ops" banner; changes made while impersonating are real) and **Events** (owner, plan, registrations vs cap,
scans, open as organizer). Staff accounts are a different role, not an organizer with extra rights: `php artisan gatezo:admin you@example.com
--name="You"` creates one and prints a set-password link (`--revoke` to remove). They cannot open `/admin` (the login
page sends them to `/ops`; they use "Log in as" instead), are hidden from the organizer list, and cannot be impersonated.

Hand-onboarding (client came through WhatsApp, we set it up for them) still exists and creates a **Pro** account:

```bash
php artisan gatezo:organizer "Bhavesh Patel" bhavesh@example.com --event="Sharad Utsav 2026" --type=festival   # --plan=free to cap
```

That creates the user and event, mails a set-password link (`SetPassword` notification) and prints the same link to paste into the chat. Re-run with the same email to issue a fresh link.

## Local dev

```bash
docker compose up -d           # MySQL 8.4 on :3308
composer install && npm install
cp .env.example .env && php artisan key:generate   # DB_* already point at the docker db
php artisan migrate:fresh --seed
npm run build                  # or `npm run dev` for HMR
php artisan serve
```

Seeded logins: organizer `organizer@gatezo.local` / `password` (event `demo-garba`, volunteer code `123456`),
staff `ops@gatezo.local` / `password` at `/ops`.

**Demo event for showing prospects** (1,500 registrations, 1,100 arrivals on a garba curve, re-entries, stalls with
leads, roster + duty logs, 100 feedback responses, one finished lucky draw with proof and one ready to run):

```bash
php artisan db:seed --class=DemoSeeder   # ~12s, re-runnable (replaces the previous demo event)
```
Login `demo@gatezo.local` / `password`, event `sharad-utsav`, volunteer code `246810`. The event is dated tonight,
or yesterday if you run it before the evening, so live widgets always have data.

Test: `php artisan test`. Format: `vendor/bin/pint`.

## Print kit templates

`events.kit_style` (Settings → Look): `classic` (black on white, any printer), `bold` (default: accent header
and footer bands, rounded QR frames) or `festival` (full accent background with a white card). All CSS in
`resources/views/print/kit.blade.php`; the browser's Print → PDF is the only pipeline, with "Background
graphics" on for the colour templates. The event's accent colour and logo flow into every sheet, and the phone
pass page uses the same pass-card look as the landing hero.

**QR codes only** (Print & reports): every code as a bare 1024 px PNG (quiet zone included) or SVG, plus a ZIP of all
with a README saying what each one is and where it points, for organizers whose designer makes the poster.
`PrintController::codes()` is the single list; PNG rendering is GD, no Imagick (`Qr::png`).

## Volunteer access control

Two ways in. **Personal link**: every roster entry on the Shifts page has one (*Send link* → WhatsApp share);
it opens the scanner as that person, pre-approved, bound to the first phone that opens it (a forwarded copy is
dead), expiring a day after the event; *New link* reissues. **6-digit code** (`events.join_by_code`, can be
switched off in Settings so only links work): 30 attempts/min per IP; 10 wrong codes → 15-minute block, every
attempt logged with device + IP. The bundle a phone downloads carries a per-pass signature, never the
event secret, so a leaked bundle cannot forge passes. Organizers can: issue a **new code** (all sessions
end), **remove** a volunteer (session ends, cannot rejoin), turn on **roster-only** (only names on the
Shifts list can join) and **approval** (new joiners wait on a holding screen until approved; no scans or
draw claims until then). Panel → People & gates → Volunteers.

## How passes work offline

`App\Services\PassToken` puts `EQ1.<code>.<hmac16>` in the QR, signed with a per-event secret.
The scanner downloads a bundle (`/scan/bundle`: pass list with per-pass signatures + gates) into IndexedDB
(memory fallback), verifies each scan by comparing signatures, queues it, and replays the queue to `/scan/sync` whenever online. Sync is
idempotent on `client_id`. Each cached pass carries its state (`inside`, `entered`, last time and gate), so a
second entry on the same pass (a forwarded screenshot) stops at the gate with an amber **Already inside** screen
and the volunteer chooses *Turn away* or *Let in anyway*; both are recorded (`checkins.decision`, turned-away rows
have `direction = denied` and never count as entries). The server applies the same rule at sync: an entry while
inside, any second entry when re-entry is off, or an exit while outside is `duplicate_flag`. Rotating
`event.pass_secret` invalidates every issued pass.

**Strict passes** (`events.strict_passes`, off by default, toggle at event creation or in Settings): the pass
page shows a rotating `EQ2.<code>.<slot>.<mac>` token (slot = 30 s window, mac = HMAC of the slot keyed by the
pass's static sig) and fetches a fresh one from `/pass/{code}/qr` as each slot ends, so a forwarded screenshot
is dead within a minute. The scanner verifies EQ2 offline with WebCrypto from the sig it already caches (±1 slot
of skew) and refuses static EQ1 tokens for strict events (`static_pass`); stale ones report `expired_pass`.
Trade-off: the attendee needs signal at the gate to show a live pass; the typed-code fallback still works.

## Layout

```
app/Filament/            panel: resources (gates, attendees, stalls, feedback, scan log), widgets, tenancy pages, Auth (login, register)
app/Filament/Ops/        staff panel at /ops: organizers, events, overview widget (OpsPanelProvider)
app/Support/Plan.php     free/pro caps in one place (events per organizer, registrations + organizers per event)
app/Support/Impersonation.php  "log in as" from Ops, with the way back
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
public/brand/            logo.png (transparent, original colours, used on light and plum), mark.png (the G), logo-original.png
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
