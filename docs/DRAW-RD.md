# Lucky draw — R&D notes (2026-09-17)

What common tools do (Lucky Draw Pro, RandomPicker, Easypromos, Whova raffles, Rafflebase,
Lucky Draw Plus), what event T&Cs in India actually say, and how that maps onto Gatezo's data.

## 1. Common logic across every tool

| Concern | What tools do | Notes for us |
|---|---|---|
| **Pool** | A list of entries (names, ticket numbers, Instagram comments). Filters remove duplicates and ineligible entries | We have better data than any of them: registered vs checked-in vs *inside right now*, feedback given, stall visited |
| **Multiple prizes** | Prize list with quantity each; drawn in order, usually smallest → grand prize last for suspense | Same. "1 × Scooter, 3 × Mixer, 10 × Vouchers" |
| **No double winners** | Winner removed from pool for later prizes; option to also exclude winners of *previous draws/events* | Default on. Per-draw switch |
| **Redraw** | If the winner is absent/ineligible/declines, forfeit and pick an alternate from the same pool | Needed: "must be present, 5 minutes to claim" is the norm in Indian T&Cs (Imagicaa, Cadence, Ustraa) |
| **Alternates** | Some draw N+2 upfront as backups | Simpler UX than live redraw when the crowd is waiting |
| **Weighting** | Extra entries for actions (visit 3 stalls, answer a survey) | Whova gates eligibility on gamification. We can weight by stall visits or feedback |
| **Manual pick** | Whova allows organizer to hand-pick with criteria | Keep out. Undermines trust; do "filters", not "pick" |
| **Presentation** | Wheel / slot / rolling names animation, confetti, winner card, share graphic | Rolling names on the gate board / projector is what a garba crowd expects |
| **Proof** | RandomPicker/Rafflebase issue a certificate; provably-fair tools publish a seed hash before the draw and reveal it after | Cheap to do: commit `sha256(seed)` when the draw is created, reveal at draw time. Makes "beta, tumhare cousin ko kaise mila?" a non-issue |
| **Winner contact** | Email/SMS/share graphic | We have the pass: "You won!" on `/pass/{code}` + WhatsApp share; volunteer verifies by scanning the winner's pass |

## 2. Rules that Indian event T&Cs consistently include

- Winner must be present at announcement; claim within N minutes (5–10) or forfeited.
- Alternate drawn at organizer's discretion from remaining eligible pool.
- One prize per person unless stated.
- Organizer's staff/volunteers not eligible.
- Prize is non-transferable; ID may be required (we use the pass + name).

## 3. Mapping to Gatezo

### Pool sources (eligibility filters, AND-ed)
- `registered` — every attendee with a pass
- `checked_in` — at least one entry scan
- `inside_now` — latest scan is `in` (best for "must be present")
- `feedback_given`, `visited_stall:{id}`, `opted_in_contact`, `ticket_type:{vip|general}`
- `exclude`: revoked passes, previous winners (this event / any event by this organizer), names on a block-list (volunteers)

### Weighting (optional, default off)
- `entries = 1 + stall_visits × w1 + feedback × w2` (cap at N)

### Draw record (auditable)
- `draws`: event, name, config JSON, `seed_hash` (committed at create), `seed` (revealed at run), `pool_snapshot` (pass codes at run time), `run_at`, `run_by`
- `prizes`: draw, name, quantity, order, image
- `draw_winners`: draw, prize, pass, rank (1 = winner, 2+ = alternate), `status` (pending → claimed | forfeited | redrawn), `claimed_at`, `verified_by` (volunteer), `claim_deadline`

Selection = deterministic shuffle of the pool snapshot using `seed` (e.g. HMAC-SHA256 stream), so anyone with the snapshot + seed can reproduce the result.

### Screens
- **Organizer**: Draws resource → create (name, pool filters, prizes, rules) → "Run" (animation on the presenter screen) → winners list with Claim / Forfeit → Redraw.
- **Presenter**: `/e/{slug}/draw/{id}` signed link for the projector/gate board: rolling names → winner card → confetti; shows the claim countdown; "next prize" button for the organizer's phone.
- **Attendee**: pass page banner "You won: Mixer! Come to the stage in 5 min" (polls); share graphic.
- **Volunteer**: scanning a winner's pass shows "WINNER: Mixer" + Claim button; claiming marks it verified.

## 4. Config module (per draw, with event-level defaults)

```
pool:        { source: inside_now | checked_in | registered, filters: [...], exclude_previous_winners: true, exclude_names: [] }
weighting:   { enabled: false, stall_visit: 1, feedback: 1, cap: 5 }
prizes:      [ { name, quantity, order, image } ]
rules:       { one_prize_per_person: true, must_be_present: true, claim_minutes: 5, alternates_per_prize: 1 }
presentation:{ style: roll | wheel, reveal_seconds: 8, show_phone_masked: true, confetti: true }
fairness:    { commit_seed: true, publish_result: true }
```

## 5. Decisions (2026-09-17)
- Default pool: **inside now**. Alternates: **drawn upfront**. Stage identity: **short name + masked phone**.
- Weighting: **later**. Claim window: **5 min**. Run/forfeit: **organizers only**. Results: **published** on `/e/{slug}/draws`.
- Defaults set without asking: rolling-names animation; exclude previous winners on.

## 5a. Open decisions (original list)
1. Pool default: `inside_now` (fair for "must be present") or `checked_in` (bigger pool, some absent)?
2. Alternates drawn upfront (recommended, smoother on stage) vs live redraw?
3. Show masked phone on stage (Ra** 98xxxx1234) to prove identity, or name only?
4. Weighting in v1 or later?
