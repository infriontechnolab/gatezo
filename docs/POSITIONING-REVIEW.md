# Gatezo positioning review

2026-09-30. Based on the code and tests (not docs or landing copy), the landing page as of commit `6700a35`, and web research on competitors.

**Bottom line:** the product is real and well built. But "Keep Eventbrite, Gatezo handles the venue" isn't true today, and Eventbrite is the wrong rival for our market. The direction is right; the wording needs to change.

The landing page currently sells "replace the clipboard" to Indian community events that "don't have a ticketing budget". That is closer to the truth than an Eventbrite-alternative story.

## 1. What actually exists

The full test suite passes: 172 tests, 0 failures, 1212 assertions.

| Status | Features |
|---|---|
| **Works** | Gate check-in/out and re-entry rules; duplicate and forwarded-pass flagging; revoked passes; offline scanning (with one bug, below); volunteers joining with a 6-digit code or a personal link, no accounts; shifts, including late and missed; gate-sign duty check-in; "Who's where" board; stall QR pages; stall-owner lead capture with attendee consent and no login; goodies (one item, one per pass, ticket-type and "checked in first" rules); lucky draw with a provable seed, snapshot, alternates and presenter screen; exit feedback (stars and comment); live dashboard refreshing every 3–5s; public gate board; printable kit (poster, gate signs, stall cards, exit card, 3 styles); post-event report (print or save as PDF); attendee CSV import and export; event duplication |
| **Partial** | **Registration:** name and phone only. The form has no email field even though the backend accepts one. No custom fields, and `events.capacity` is displayed but never enforced (only the plan's attendee limit is). **Pass delivery:** a pass page and a WhatsApp *share* button only; nothing is sent automatically. **Multiple gates:** no per-gate ticket-type rules, any volunteer can pick any gate, and `SyncScansRequest` doesn't check that `gate_id` belongs to the event. **Team:** one Organizer role with full access. **Billing:** a manual request, then UPI or bank payment, then Ops confirms it. **Goodies:** one item per event; stock is only approximate offline. |
| **Missing** | Any integration (no API, webhooks or Eventbrite sync); scanning a ticket platform's own barcode; attendee email, SMS or WhatsApp sending; paid ticketing |

**Bugs and loose ends:**
- **Offline scanning drops some scans (`resources/js/scanner.js:225`).** If a valid pass isn't in the phone's cached list, the scanner shows "will sync" but returns before saving the scan, so it is lost. The comment at line 213 says these scans are queued. The list refreshes every 60s while online, which limits the impact.
- **The report's "walk-ups" count is always 0.** Nothing ever marks an attendee as `AttendeeSource::Walkup`; public registration records `Online`.
- Leftover vendor-login code (`stalls.vendor_user_id`, `MemberRole::Vendor`) is never used.

## 2. Competitors

- **Eventbrite's check-in app is better than assumed.** It works offline, syncs across devices, has a check-out mode and can limit ticket types per device. What it lacks: volunteers and shifts, stall leads, goodies, lucky draw, exit feedback and sponsor reports. Lead with that missing half, not with "better scanning".
- **KonfHub is our real competitor in India.** It already has offline check-in, multiple check-in stations and gates, goodies tracking, volunteer access codes, sponsor lead capture, feedback and webhooks. It charges 2% + GST per paid ticket and is free for free events up to 100 registrations.
  - No sign found at KonfHub of: lucky draw, a live "inside now" count with re-entry, volunteer shifts, printed kits, or importing lists from other platforms. The research couldn't fully verify this, so treat it as provisional.
- **Enterprise tools** (Cvent, Swapcard, Zoho Backstage, Eventify) cover everything but cost ₹8k+ a month or are quote-only. **Cheap tools** (Luma, Ticket Tailor, Townscript) only scan.
- **Eventbrite's ownership changed.** Bending Spoons completed its purchase in March 2026, followed by layoffs. That creates doubt among Eventbrite users, but also makes building on Eventbrite's API riskier; the API is reported to be rate-limited and poorly supported.

### Competitor table

| Name | Target | Offline | Multi-gate / zones | Volunteers | Exhibitor leads | Pricing | Works with Eventbrite |
|---|---|---|---|---|---|---|---|
| Eventbrite Organizer app | All sizes | Yes | Per-device ticket-type limits; no gate concept found | No | No | Free events free; paid 3.7% + $1.79 + 2.9% (US) | — |
| KonfHub (India) | Conferences, expos | Yes | Yes (stations, security gates) | Access codes | Yes | Free events free (≤100); 2% + GST and up | No (ticketing platform) |
| Townscript (BookMyShow) | Indian SMB events | Unverified | Unverified | No | No | Unverified | No (Zapier trigger only) |
| Luma | Meetups, community | Passes yes; scanner unverified | Per-staff ticket types | Check-in role | No | Free; 5% on paid; Plus $59/mo for API | No |
| Zoho Backstage | Mid to enterprise | Unverified | Sessions | Limited | Yes | ₹7,999–₹29,999/mo | No |
| Eventify (India) | Large conferences and expos | Yes | RFID | Supplies staff | Yes | Custom quotes | Not stated |
| Eventleaf | SMB conferences | Unverified | Unverified | — | Unverified | $1–2 per attendee | Yes (CSV import) |
| zkipster | VIP guest lists | Yes | Yes | — | — | ~$233–733/mo | Yes (Zapier) |
| Cvent OnArrival | Enterprise | Yes | Sessions | — | Separate product | Quote | No |
| Whova | Conferences | Unverified | Sessions | Restricted roles | Yes | Quote | Unverified |
| Swapcard | Expos | Unverified | Session access | — | Yes | ~€490/yr and up | Unverified |

Main sources: [Eventbrite onsite tools](https://www.eventbrite.com/blog/onsite-operations-tools-eventbrite/), [Eventbrite Help 741083](https://www.eventbrite.com/help/en-us/articles/741083/), [Eventbrite pricing](https://www.eventbrite.com/organizer/pricing/), [KonfHub check-in](https://help.konfhub.com/apps/check-in-app.md), [KonfHub lead app](https://help.konfhub.com/apps/lead-capture-app), [KonfHub webhooks](https://help.konfhub.com/integrations/webhooks), [Luma check-in](https://help.luma.com/p/check-in), [Swapcard pricing](https://swapcard.com/pricing), [Ticket Tailor on the Eventbrite sale](https://www.tickettailor.com/blog/bending-spoons-acquires-eventbrite-what-does-this-mean).

### Gaps in the market, with evidence

1. **Crowd control at college fests and cultural events.** CUSAT stampede, Nov 2023 (4 dead; one gate for entry and exit, run by student volunteers): [Deccan Herald](https://www.deccanherald.com/india/kerala/4-students-killed-64-injured-in-stampede-during-college-tech-fest-in-kerala-2785070). IIT Bombay Mood Indigo pre-registrations cancelled after crowds at the gate: [Careers360](https://news.careers360.com/iit-bombay-cancels-mood-indigo-pre-registrations-stops-entry-outsiders-crowd-chaos-seedhe-maut-concert-sonu-nigam-vicky-kaushal). No low-cost Indian tool is marketed for this.
2. **Unreliable venue internet.** Real, but offline scanning is now standard, so it is expected rather than a differentiator.
3. **Exhibitor leads at small expos.** Rented scanners cost $350–735 per rep per show; the cheap option is a fishbowl of business cards.
4. **Volunteer coordination** runs on WhatsApp and spreadsheets. The pain is real; the evidence that people will pay to fix it is weak.
5. **Sponsor proof and post-event reports.** Probably the strongest reason an organizer would pay.
6. **Goodies and lucky draws.** Good in demos; they won't win customers on their own.

## 3. The two positioning statements

**"The on-ground operating layer for events": right as an internal strategy line, wrong as a headline.** A garba committee or fest convenor doesn't think in "layers". Keep it for pitch decks.

**"Already using Eventbrite? Keep it. Gatezo handles the venue." Don't use this.** Three reasons:
1. **It isn't true yet.** The scanner only reads Gatezo's own passes (`EQ1`/`EQ2` in `parseToken`, `scanner.js:55`), and the importer has no barcode column. An Eventbrite attendee would end up with *two* QR codes, and there's no way to send them the Gatezo one.
2. **Eventbrite isn't who our customers use.** In India they register on Google Forms, Townscript, KonfHub or WhatsApp lists.
3. **Eventbrite already scans offline.** The comparison invites "mine already does that".

A version that is honest today: *"Already have a list? Google Form, Townscript, Eventbrite: import the CSV and Gatezo issues the passes."* To make "keep your ticketing" genuinely true later, accept the source platform's barcode on import so existing tickets scan at Gatezo gates.

## 4. The attendee journey today

| Stage | Status |
|---|---|
| Registration | ✅ Self-registration (name + phone) or CSV import · ⚠️ no email field, custom fields or enforced capacity |
| QR pass | ✅ Signed pass; optional pass that changes every 30s · ⚠️ nothing sent automatically, only the page and a WhatsApp share button |
| Entry | ✅ Scanning works offline · 🐞 unknown-pass scans are dropped |
| Gates | ✅ Per-gate entries · ⚠️ no per-gate "inside now" count, ticket-type rules or volunteer assignment |
| Volunteers | ✅ Strong: no accounts, shifts, duty check-in, who's-where board |
| Stalls | ✅ Visitor stall page, consented leads, CSV per stall · ⚠️ the organizer can't export all leads at once |
| Goodies | ✅ One item with rules · ⚠️ single item only, stock only approximate offline |
| Exit | ✅ Check-out scans · feedback isn't linked to them |
| Feedback | ✅ Basic stars and comment |
| Reports | ✅ Printable report · ⚠️ walk-ups always 0, no report per sponsor |

## 5. What to build and what to skip

**Missing and worth building, in priority order:**
1. Fix the dropped offline scan (queue the scan and let the server verify it).
2. Correct the landing page (section 6).
3. A **sponsor and exhibitor report**: an export of all leads for the organizer, plus a shareable page per stall showing footfall, leads and feedback. This is the likeliest thing organizers will pay for, because sponsors fund events.
4. **Walk-up registration at the gate** by a volunteer. It speeds entry and fixes the walk-up number.
5. **Per-gate "inside now" and capacity alerts.** Word it carefully and don't make safety promises.
6. Import presets for Google Forms, Townscript and Eventbrite CSVs, then accepting the platform's barcode on import.
7. An optional email field on the registration form.

**Not needed now:**
- Paid ticketing and payments.
- An Eventbrite API sync: fragile, and aimed at the wrong market.
- A native app.
- Badge printing, kiosks, RFID or face check-in.
- Agenda, sessions, networking or virtual events.
- Wallet passes.
- WebSockets (3-second polling is enough).
- Detailed team roles.
- Sending passes through the paid WhatsApp Business API; the share link is enough for now.

## 6. Landing page corrections

| Line (`resources/views/landing.blade.php`) | Problem | Fix |
|---|---|---|
| L199, L328, L334 floor-plan section ("printed from Gatezo", "Sheet 2 of 4", "Scale 1:400") | **Not supported**: Gatezo has no map feature | "Your own site plan with Gatezo's QR stickers on it" |
| L79 "Live headcount… per gate" | "Inside now" is event-wide; per gate only counts entries | "Inside now, plus entries per gate, as it happens" |
| L404 "vendor lead lists" | Only each stall owner can download their own leads | "Each stall owner downloads their own leads" (or build the organizer export) |
| L394 "24 shared passes caught" | The count includes all repeat entries | "repeat entries flagged at the gate" |
| L490 "pick [a plan] after you sign up" | There's no checkout | "request one and we'll set it up (UPI or bank transfer)" |
| L425 "Scanners cache the list before doors open" | Only if each phone was opened online first | "Open the scanner once on Wi-Fi before doors open…" |
| Chaos panel (L152–158), map tallies (L313–323), ticker (`resources/js/landing.js`) | Made-up numbers not visibly labelled as demo | Add a visible "demo" tag |
| Hero "Scan the pass on the right, it works" | The phone QR is a dummy; the working QR points at `/e/sharad-utsav`, which gives a 404 if the demo isn't seeded | Point it at a real demo pass, or reword |

Everything else on the page checks out against the code. There are no testimonials, customer counts or compliance claims.

## 7. Recommendation

- **Target customer:** Indian organizers of free or low-price, volunteer-run events for 200–5,000 people, with several entry points or stalls. Examples: housing-society festivals, college fests, community melas, small expos, alumni meets, temple events. The buyer is the committee head or fest convenor. Second target: small-expo organizers whose stall owners want leads.
- **Core problem:** on the day, nobody knows who's inside, which gates are busy, or whether volunteers turned up. Afterwards there are no numbers to show sponsors. Today it all runs on paper lists and WhatsApp groups.
- **Positioning:** *Gatezo runs the venue.* It covers gates, volunteers, stalls and the sponsor report, starting from four printed sheets and your volunteers' phones. It works whatever you registered with.
- **Main differentiators:**
  - Volunteers join without accounts, and shifts are tied to gates.
  - Offline scanning with re-entry rules and a live "inside now" count.
  - Consented stall leads with no exhibitor login.
  - A provable lucky draw.
  - A printed kit.
  - A flat ₹199 a month with no per-ticket cut, versus KonfHub's 2% + GST.
- **Competitor strategy:** don't fight Eventbrite; list it as one of the sources we can import from. Position against **paper lists, WhatsApp groups and Google Forms**. Against KonfHub: "you don't have to move your registration, and you don't pay per ticket."
- **Suggested landing-page messaging:**
  - Headline: *"Run the venue, not the clipboard."*
  - Subhead: *"Gates, volunteers, stalls and the sponsor report, from four printed sheets and your volunteers' phones. Keeps scanning when the Wi-Fi doesn't."*
  - Section: *"Already have a list? Import it from Google Forms, Townscript or Eventbrite."*
  - Pricing: *"Flat ₹199 a month. No cut of your tickets."*
  - Before adding a sponsor-report section, build priority 3.

## Unverified

- Eventbrite's official API status in 2026 (the developer page returned 401; community reports say it is rate-limited and unsupported).
- KonfHub's exact feature gaps (lucky draw, live "inside now" count).
- India pricing for Eventbrite and Townscript.
- Whether Luma's scanner works offline.
- District / BookMyShow on-site tools for organizers.
