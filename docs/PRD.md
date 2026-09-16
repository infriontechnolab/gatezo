# PRD — QR Event Management System (PWA)

**Owner:** Infrion Technolab
**Status:** Scaffolded 2026-09-16 (Laravel + Filament), core flows tested
**Date:** 2026-06-18

---

## 1. Problem & Goal

Replace manual phone-based coordination + paper data entry with a QR-code-driven
digital workflow for local events. One platform, many events over time (multi-event).

**Event types:** community gatherings, sports tournaments, festivals & fairs,
workshops & training, religious events, college events.

---

## 2. Tech Stack (locked 2026-09-16, supersedes the Next.js plan)

| Layer | Choice |
|-------|--------|
| Framework | Laravel 13 (PHP 8.3 local / 8.4 prod), single repo |
| Organizer panel | Filament 5, `Event` as Filament tenant (multi-event switcher) |
| Public pages | Blade + Alpine, per-event accent colour |
| Scanner | Blade page + hand-written JS: BarcodeDetector/jsQR, IndexedDB queue, WebCrypto HMAC check |
| DB | MySQL 8 (Docker locally, same box in prod) |
| Realtime | Livewire polling (3s) on dashboard widgets; no websockets |
| Offline | Service worker caches pass + scanner shell; attendee list cached in IndexedDB |
| Hosting | One VPS (GCP Compute Engine or Hostinger): nginx + PHP-FPM + MySQL + Supervisor |

**Why:** Laravel is the team's revenue stack; Filament covers ~70% of screens (all admin CRUD,
tables, widgets, exports) out of the box; one repo, no CORS/API contract; a ₹400–800/mo VPS is
always warm and cheaper than any managed DB. Only the scanner needs custom JS.

---

## 3. Roles

- **Organizer (admin)** — full control panel, multi-event.
- **Volunteers / staff** — gate scanning, shift check-in, task board.
- **Attendees (public)** — register, hold pass, scan stalls, give feedback. No heavy auth.
- **Vendors / stall owners** — stall page, lead capture, vendor dashboard.

---

## 4. QR Functions

| QR | Scanned by | Action |
|----|-----------|--------|
| Event registration | Attendee (public) | Self-register → digital pass with own QR |
| Attendee pass | Volunteer @ gate | Check-in / validation |
| Gate / zone | Volunteer | Shift "on-duty" check-in, timestamped |
| Stall public | Attendee | View stall info, menu/products, location, offers (no login) |
| Vendor lead | Vendor scans attendee | Lead capture (attendee opt-in) |
| Feedback | Attendee | Rating + comment form |

---

## 5. Features (step-by-step)

### 5.1 Attendee Registration (both paths)
- **Pre-register online:** sign up ahead → unique QR pass emailed/shown.
- **Walk-up:** scan public event QR at gate → quick form → instant pass.
- Free events only. **No payment gateway.**
- Pass = QR encoding a **signed token** (attendee id + signature) so it validates offline.

### 5.2 Check-in / Scanning
- Volunteer opens PWA → camera scans pass QR.
- On scan:
  - **a)** Validate — real? already used? duplicate?
  - **b)** Mark attendance + timestamp.
  - **c)** Show attendee info (name, ticket type, VIP flag).
  - **d)** Live capacity counter (current vs max).
  - **e)** Re-entry — scan out / scan in again.
- **Multiple gates concurrent.** Live count syncs via SSE.

### 5.3 Stalls / Vendors
- Each stall: **public QR** (attendee → stall page) + **vendor QR** (vendor scans attendee → lead capture, opt-in).
- Vendor dashboard: scan count, leads list.

### 5.4 Volunteer Coordination
- Login → assigned shifts + task board.
- Scan zone QR → marks "on duty" at that post (organizer sees who's where, live).
- Task board: organizer posts tasks → volunteer claims → marks done.

### 5.5 Feedback
- Event feedback QR (exit) + auto-prompt in pass after check-out.
- Star rating (1–5) + category ratings (venue, organization, food) + comment.
- Tied to attendee if logged in (anti-spam, follow-up) with **anonymous fallback**.
- Dashboard: avg scores, charts, recent comments.

### 5.6 Admin Dashboard (all, multi-event)
- **Event setup** — type, accent+logo, capacity, dates, gates.
- **Live monitor** — realtime check-in count, capacity gauge, per-gate stats, who's-on-duty.
- **People** — attendee list (registered/checked-in), search, CSV export.
- **Volunteers** — assign shifts, task board, duty status.
- **Vendors/stalls** — add stalls, generate stall QRs, lead counts.
- **QR generator** — make/download/print all QR codes.
- **Feedback** — ratings charts, comments feed.
- **Reports** — post-event summary (total attendance, peak time, feedback score), PDF/CSV export.

---

## 6. Offline Strategy (B+)

- App shell cached (service worker) → opens with no internet.
- Pass QR displays offline (image from cache).
- Pre-event: download attendee list to IndexedDB → validate "real?" + "used? (per-phone)" locally.
- Scans queue in IndexedDB → auto-sync on reconnect.
- **Cross-gate duplicate** flagged at sync time (no realtime dedup possible while both gates offline — accepted tradeoff).

---

## 7. Out of Scope (v1)

- Payments / paid ticketing.
- Native mobile apps (PWA only).
- True realtime cross-gate offline dedup (flag-at-sync instead).
- Websockets (SSE instead).

---

## 8. Open Items / To Confirm Later

- Email/SMS provider for sending unique QR passes (pre-registration).
- Auth method for organizers/volunteers (email+password? magic link?).
- Region for Cloud Run + Cloud SQL.
- Domain / subdomain.
