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

Plan limits are caps, not clocks, so an organizer who signs up two months before the event is not cut off before
their first scan. The starting catalogue, at launch prices (edit them in Ops → Plans):

| | Free | Starter | Pro |
|---|---|---|---|
| Events created | 1 | 3 | unlimited |
| Registrations per event (form, CSV, manual) | 200 | 1,000 | unlimited |
| Organizers per event | 1 | 3 | unlimited |
| Price | ₹0 | ₹199 / month or ₹1,499 / year | ₹499 / month or ₹3,999 / year |
| Scanner, stalls, feedback, draw, reports | all | all | all |

An event is governed by the plan of the user who **created** it; being invited to someone's event uses none of your
own allowance. Owners near their cap see a usage strip under the topbar with a "See plans" link (also in
the user menu); when the cap bites, the public form says "Registration is full", CSV import stops and says so, the
Team page swaps *Invite* for *Upgrade*, and "Create event" / "Duplicate event" disappear.

**Plans** (Free, Starter, Pro…) live in the `subscription_plans` table and are edited in Ops → **Plans**: name, caps
(empty = unlimited), monthly and yearly price (empty = not sold on that cycle), on sale, order. Every upgrade link
leads to the in-panel **Plans** page; the organizer picks a plan and billing cycle and leaves a phone number. That
records an `upgrade_request` (mailed to `GATEZO_SIGNUP_NOTIFY`); there is no payment gateway, so we call back and take
payment by UPI or bank transfer.

A paid plan is a dated **subscription**: Ops → **Plan requests** → *Confirm payment* records the period (plan, billing,
from, until, amount, payment reference), pre-filled from what they chose; Ops → **Organizers** → *Add paid period /
Renew* does the same without a request, and Ops → **Subscriptions** lists every period with *Change dates* and *End
today*. An organizer is on a paid plan only while today falls inside a period (if an upgrade overlaps, the higher plan
wins), so it switches itself off after the last day; the owner sees an "ends on …" strip a week before.
`users.plan` stays as the base plan: `free`, or a comped plan with no end date:

```bash
php artisan gatezo:plan bhavesh@example.com pro     # no plan argument just shows where they stand
```

**Ops panel** (`/ops`, staff only): dashboard (pending plan requests, sign-ups this week/month, organizers on a paid
plan, upcoming and live events, registrations), **Organizers** (current plan and paid-until date, *Add paid period /
Renew*, WhatsApp link, set-password link, **Log in as** → opens their panel with a "Back to Ops" banner; changes made
while impersonating are real), **Events** (owner, plan, registrations vs cap, scans, open as organizer), **Plan
requests**, **Subscriptions** (badge: periods ending within 14 days) and **Plans**. Staff accounts are a different role, not an organizer with extra rights: `php artisan gatezo:admin you@example.com
--name="You"` creates one and prints a set-password link (`--revoke` to remove). They cannot open `/admin` (the login
page sends them to `/ops`; they use "Log in as" instead), are hidden from the organizer list, and cannot be impersonated.

Hand-onboarding (client came through WhatsApp, we set it up for them) still exists and creates a **Pro** account
with no end date (`users.plan = pro`, not a subscription):

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

Test: `vendor/bin/pest` (runs the older PHPUnit class tests too; new tests are written in Pest, see `tests/Pest.php`
for `actingAsOrganizer()`, which also boots the admin panel so tenant scoping works in Livewire tests).
Format: `vendor/bin/pint --dirty`.

## AI-assisted development

[Laravel Boost](https://laravel.com/docs/boost) (dev dependency) gives coding agents an MCP server (`.mcp.json`:
docs search, DB schema, routes, logs) plus guidelines and skills:

- **Rules:** the team's coding rules live in `.ai/guidelines/project-conventions.md`. Boost merges them with its own
  Laravel/Pest/Pint guidelines into `AGENTS.md`, which `CLAUDE.md` imports. Edit the file in `.ai/guidelines/`, then
  run `php artisan boost:install --guidelines -n`; don't edit `AGENTS.md` by hand.
- **Skills** (`.claude/skills/`, mirrored in `.agents/skills/`): `filament-development` (from Filament),
  `laravel-best-practices`, `testing-best-practices`, `tailwindcss-development`, `infer-conventions`.
  Re-syncing with `boost:install --skills -n` drops `filament-development`; run `php artisan boost:install`
  interactively and tick it, or copy it back from `vendor/filament/filament/resources/boost/skills/`.
- **Config:** `boost.json` (which skills, integrations) and `config/boost.php`, which excludes Boost's Laravel Cloud
  guideline since we deploy to our own servers.

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

## Data quality

Every form that takes a person's name (public registration, CSV import, volunteer join, organizer sign-up, team
invite, shift roster) uses `App\Rules\PersonName`: letters in any script plus spaces and `. ' -`, at least two
letters, so `%%%$$$$` or `123` never becomes an attendee. Every phone field uses `App\Rules\PhoneNumber`: 8 to 15
digits, no placeholders (`0000000000`, `1234567890`, `9876543210`), and a ten-digit number is an Indian mobile
starting with 6–9 (add a country code otherwise).

Rows saved before those rules existed can be listed (read-only) with an edit link each:

```bash
php artisan gatezo:junk-names                 # every event; --event=<slug> for one
```

Anyone marked "Scanned in: yes" came through a gate: fix the name rather than delete them, or gate counts change.

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
app/Filament/Ops/        staff panel at /ops: organizers, events, plan requests, subscriptions, plans, overview widget
app/Enums/               every fixed set of values (ticket type, check-in direction, draw status, …), with Filament label/colour/icon
app/Support/Plan.php     plan caps in one place, read from the subscription_plans catalogue
app/Support/Impersonation.php  "log in as" from Ops, with the way back
app/Http/Controllers/    PublicEventController (attendees), ScannerController (volunteers),
                         VendorController (signed link + leads), PrintController (kit, report, CSV)
app/Http/Requests/       SyncScansRequest (the scanner's offline queue)
app/Services/            PassToken, Qr, DrawEngine, AttendeeImporter, and the scanner's VolunteerAccess (code join,
                         invite links), ScannerBundle (offline bundle), ScanRecorder (one synced scan)
app/Rules/               PersonName, PhoneNumber (shared by every form that takes a name or phone)
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
.ai/guidelines/          project coding rules for AI agents (merged into AGENTS.md by Boost)
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

Organizer tools: CSV import (loose headers, dedupe by phone; a ticket label Gatezo doesn't know, like "Gold", is stored
as General with the original kept in `extra.ticket` and written back out in the attendees CSV), revoke/restore pass, invalidate all passes,
vendor link regenerate, Team page (invite co-organizer via set-password link, no email needed), duplicate event.

Shifts: roster by name (links to the volunteer when they join), status Upcoming / Starting / On duty /
At another post / Late / Missed / Done, gate preselect + banner in the scanner, "Not arrived" on the board,
roster in the post-event report.
Lucky draw: pool = inside now / checked in / registered (+ filters), prizes in order, winner + backups drawn
upfront from a seed whose hash is committed at create and revealed at finish; Stage page (Run → Announce next →
Claimed / Forfeit), signed presenter screen with rolling names + countdown, pass banner for the winner, volunteer
claim by scanning the pass, public results page with proof. Design notes in docs/DRAW-RD.md.
Gate board: signed public link (Print & reports) to a full-screen "inside now" page for a tablet at the entrance.

## Deploying

`infra/deploy.sh` (see `infra/SERVER.md`) installs, migrates and caches. One-time steps after the release that
added paid plans:

1. Set real prices and caps in Ops → Plans (it starts at the launch prices above).
2. In `.env`: keep `GATEZO_SIGNUP_NOTIFY` set so plan requests reach the inbox; `GATEZO_PRO_PRICE` is no longer used.
3. Organizers switched to Pro by hand before subscriptions existed have no end date (`users.plan = pro`). Record a
   paid period for them in Ops → Organizers, then `php artisan gatezo:plan <email> free`, if they should expire.
4. The ticket-type migration turns imported labels Gatezo doesn't know into General and keeps each original in
   `extra.ticket` (its `down()` restores them).
5. Run `php artisan gatezo:junk-names` and clean up what it lists.

## Not built yet

- Pass delivery by SMS/WhatsApp API (currently: share button + phone-number lookup)
- SMTP config (password reset currently logs to file)
