# Gatezo — how it all fits together

Four audiences, one Laravel app. Every printed QR is a URL into one of these flows.
Rendered PNGs live in `docs/flows/`:

| File | What |
|---|---|
| `1-system-map.png` | Five lanes (organizer, print kit, attendee, volunteer, vendor/tablet) and every handoff between them |
| `2-attendee.png` | Register → pass → gate → stalls → feedback |
| `3-volunteer.png` | Join → offline scanner → sync, including the failure branches |
| `4-event-day.png` | T-7 days to next morning |
| `5-data-model.png` | Tables and what each printed QR points at |

Re-render: open the HTML for the map; for the Mermaid ones paste a block into https://mermaid.live or any Markdown viewer.

## 1. System map: who touches what

Hand-laid swimlane in `docs/flows/system-map.html` (open in a browser, or see `docs/flows/1-system-map.png`).
The Mermaid version below is the same information for tools that render Markdown.

```mermaid
flowchart TB
    subgraph ORG["ORGANIZER · /admin · login"]
        direction LR
        O1["Event setup<br/>gates · capacity · walk-up · re-entry"]
        O2["Attendees<br/>CSV import · revoke · export"]
        O3["Stalls<br/>menu · offers · vendor link"]
        O4["Shifts<br/>roster by name"]
        O5["Team<br/>invite co-organizer"]
        O6["Print & reports<br/>kit · report · board link"]
        O7["Dashboard<br/>inside now · who's where · charts"]
    end

    subgraph KIT["PRINT KIT · A4 sheets stuck on things"]
        direction LR
        P1["Poster QR"]
        P2["Gate / zone sign QR"]
        P3["Stall card QR"]
        P4["Exit feedback QR"]
    end

    subgraph ATT["ATTENDEE · no login"]
        direction LR
        A1["/e/{slug}<br/>register"]
        A2["/pass/{code}<br/>QR pass · consent toggle"]
        A3["/stall/{code}<br/>menu & offers"]
        A4["/e/{slug}/feedback<br/>stars + comment"]
    end

    subgraph VOL["VOLUNTEER · 6-digit code"]
        direction LR
        V1["/scan<br/>join"]
        V2["/scan/app<br/>offline scanner"]
    end

    subgraph EXT["VENDOR & GATE TABLET · signed links"]
        direction LR
        W1["/vendor/{code}<br/>leads · CSV · lead scanner"]
        T1["/e/{slug}/board<br/>inside now, full screen"]
    end

    O6 --> KIT
    P1 --> A1 --> A2
    P3 --> A3
    P4 --> A4
    P2 -- "on duty" --> V2
    V1 --> V2
    A2 -- "shown at gate" --> V2
    A2 -- "shown at stall, if opted in" --> W1
    O3 -- "link" --> W1
    O6 -- "link" --> T1
    O4 -. "shift banner" .-> V2
    V2 -- "scans + duty sync" --> O7
    A4 --> O7
    W1 -- "leads" --> O3
```

## 2. Attendee journey

```mermaid
flowchart TD
    S(["Sees poster or WhatsApp link"]) --> R["/e/{slug} register<br/>name + phone, no OTP"]
    R -->|phone already registered| P
    R -->|new| C["Create attendee + pass<br/>code = 8 chars, unambiguous alphabet"]
    C --> P["/pass/{code}<br/>QR = EQ1.CODE.hmac16 of event secret"]
    P --> H["Add to home screen or send to WhatsApp<br/>works offline"]
    P --> T{"Event has stalls?"}
    T -->|yes| K["Toggle: allow stalls to contact me<br/>default OFF"]
    P --> G["Show QR at gate, volunteer scans"]
    G --> E["Inside. Re-entry if enabled"]
    E --> ST["Scan stall card: /stall/{code}"]
    E --> VS["Vendor scans pass: lead only if opted in"]
    E --> X["Exit card: /e/{slug}/feedback<br/>anonymous unless via pass link"]
```

## 3. Volunteer journey (the offline part)

```mermaid
flowchart TD
    J["/scan · type 6-digit code + name"] --> U["Synthetic user = name + event + device cookie<br/>links any shift with that name"]
    U --> A["/scan/app opens"]
    A --> B["Download bundle to IndexedDB<br/>event secret · pass list · gates"]
    B --> CAM["Camera: BarcodeDetector, jsQR fallback"]
    CAM --> Q{"What was scanned?"}
    Q -- "gate sign URL" --> D["POST /scan/duty: on duty here<br/>soft warning if not the rostered gate"]
    Q -- "pass token" --> V{"HMAC valid with cached secret?"}
    V -- no --> RED["Red flash: invalid pass"]
    V -- yes --> L{"Code in cached list?"}
    L -- no --> AMB["Amber: unknown, will sync"]
    L -- yes --> OK["Green flash: name, VIP<br/>scan queued in IndexedDB"]
    OK --> F{"Online?"}
    F -- no --> W["Wait. Queue survives reloads. Retry every 10s"]
    W --> F
    F -- yes --> SY["POST /scan/sync: batch, idempotent by client_id"]
    SY --> RES["Server replies per scan:<br/>ok · duplicate (flagged) · turned_away · already_synced · invalid · revoked"]
    SY -- "401 / 419" --> EXP["Banner: session expired, rejoin. Queue kept"]
    EXP --> J
```

## 4. Event day timeline

```mermaid
flowchart LR
    W1["T-7d<br/>Create event, gates, stalls, shifts.<br/>Print kit → print shop."] --> W2["T-6d<br/>Forward /e/{slug} on WhatsApp.<br/>Import existing list (CSV)."]
    W2 --> W3["T-1d<br/>Test: join scanner on own phone,<br/>scan a gate sign + own pass."]
    W3 --> W4["Doors open<br/>Volunteers join with code,<br/>scan gate signs → Who's where."]
    W4 --> W5["Live<br/>Dashboard + gate tablet board.<br/>Wi-Fi dies → scanners keep working."]
    W5 --> W6["Exit<br/>Feedback cards. Vendors export leads."]
    W6 --> W7["Next morning<br/>Post-event report PDF → committee.<br/>Duplicate event for next year."]
```

## 5. Data model (what each QR points at)

```mermaid
erDiagram
    EVENT ||--o{ GATE : has
    EVENT ||--o{ ATTENDEE : has
    ATTENDEE ||--|| PASS : "one pass"
    PASS ||--o{ CHECKIN : "scans (in/out)"
    GATE ||--o{ CHECKIN : at
    EVENT ||--o{ STALL : has
    STALL ||--o{ LEAD : captures
    ATTENDEE ||--o{ LEAD : "opted in"
    EVENT ||--o{ SHIFT : plans
    EVENT ||--o{ DUTY_LOG : "actual presence"
    EVENT ||--o{ FEEDBACK : receives
    EVENT }o--o{ USER : "event_members (organizer / volunteer)"
    USER ||--o{ DUTY_LOG : volunteer
    USER ||--o{ CHECKIN : scanned_by

    EVENT { string slug  string pass_secret  string volunteer_code  int capacity }
    PASS { string code  bool revoked }
    CHECKIN { enum direction  uuid client_id  bool duplicate_flag  datetime scanned_at }
    STALL { string public_code  int link_version  int view_count }
    SHIFT { string volunteer_name  datetime starts_at  datetime ends_at }
    ATTENDEE { string phone  bool share_contact  enum source }
```
