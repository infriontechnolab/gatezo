/**
 * Scanner component, two modes:
 *   mode:'gate'  volunteer check-in (default). Also recognises printed gate-sign URLs → "on duty here".
 *   mode:'lead'  vendor lead capture: scanning a pass posts to leadUrl; requires attendee opt-in.
 *
 * Designed for "the venue Wi-Fi died":
 *   - bundle (event secret + pass list + gates) cached in IndexedDB
 *   - every scan verified locally: HMAC signature via WebCrypto, then pass lookup
 *   - scans appended to an IndexedDB queue and flushed whenever we're online
 *   - each cached pass carries its state (inside/entered); a second entry shows an amber
 *     'already inside' screen and the volunteer decides. Turned-away scans are recorded too.
 */
import jsQR from 'jsqr';
import { openDB } from 'idb';

const DB_NAME = 'gatezo-scanner';
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';

/**
 * Storage: IndexedDB when it works, memory when it doesn't (private modes, some
 * Safari builds, or an IDB that simply hangs). Memory still gives a working scanner
 * for the session; only the "survives a reload" guarantee is lost, and we say so.
 */
const memory = { bundle: new Map(), queue: new Map() };
let idb = null, idbTried = false, idbFailed = false;
async function getDb() {
    if (idbTried) return idb;
    idbTried = true;
    try {
        idb = await Promise.race([
            openDB(DB_NAME, 1, { upgrade(d) { d.createObjectStore('bundle'); d.createObjectStore('queue', { keyPath: 'client_id' }); } }),
            new Promise((_, rej) => setTimeout(() => rej(new Error('idb timeout')), 2500)),
        ]);
    } catch (e) {
        idb = null; idbFailed = true;
        console.warn('[scanner] IndexedDB unavailable, using memory:', e?.message);
    }
    return idb;
}
const store = {
    async get(s, k) { const d = await getDb(); return d ? d.get(s, k) : memory[s].get(k); },
    async put(s, v, k) { const d = await getDb(); if (d) return d.put(s, v, k); memory[s].set(k ?? v.client_id, v); },
    async getAll(s) { const d = await getDb(); return d ? d.getAll(s) : [...memory[s].values()]; },
    async count(s) { const d = await getDb(); return d ? d.count(s) : memory[s].size; },
    async del(s, k) { const d = await getDb(); if (d) return d.delete(s, k); memory[s].delete(k); },
};

function urlPath(raw) {
    try { const u = new URL(raw); return /^https?:$/.test(u.protocol) ? u.pathname : null; } catch { return null; }
}

// Mirrors App\Services\PassToken: static "EQ1.<code>.<sig16>" or rotating "EQ2.<code>.<slot>.<mac16>"
function parseToken(raw) {
    const parts = String(raw).trim().split('.');
    if (parts.length === 3 && parts[0] === 'EQ1') return { v: 1, code: parts[1].toUpperCase(), sig: parts[2].toLowerCase() };
    if (parts.length === 4 && parts[0] === 'EQ2' && /^\d+$/.test(parts[2])) return { v: 2, code: parts[1].toUpperCase(), slot: Number(parts[2]), mac: parts[3].toLowerCase() };
    return null;
}
const SLOT_SECONDS = 30, SLOT_WINDOW = 1;
const currentSlot = () => Math.floor(Date.now() / 1000 / SLOT_SECONDS);
// mac = first 16 hex of HMAC-SHA256(slot, sig): the cached per-pass sig is the key, so this works offline.
async function rotatingMac(sig, slot) {
    const enc = new TextEncoder();
    const key = await crypto.subtle.importKey('raw', enc.encode(sig), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
    const buf = await crypto.subtle.sign('HMAC', key, enc.encode(String(slot)));
    return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('').slice(0, 16);
}

window.Alpine?.data?.('scanner', scannerComponent) ?? document.addEventListener('alpine:init', () => Alpine.data('scanner', scannerComponent));

function scannerComponent(cfg) {
    return {
        online: navigator.onLine,
        bundle: null,
        gates: [],
        gateId: '',
        direction: 'in',
        pending: 0,
        recent: [],
        flash: null,
        cameraError: '',
        manualCode: '',
        sessionExpired: false,
        storageWarning: false,   // IndexedDB unavailable: queue lives in memory only
        winner: null,          // { code, name, prize, token } when the scanned pass is on stage
        hold: null,            // { title, name, detail, scan } while the volunteer decides on an already-inside pass
        _passIndex: new Map(),
        _lastToken: null,
        _lastAt: 0,
        _flushing: false,

        mode: cfg.mode ?? 'gate',

        async init() {
            if (cfg.duty) this.showFlash('ok', 'On duty', cfg.duty);
            window.addEventListener('online', () => { this.online = true; this.refreshBundle(); this.flush(); });
            window.addEventListener('offline', () => (this.online = false));
            // Cached and fresh bundles race; whichever lands first is used, fresh always wins.
            await Promise.allSettled([this.loadBundle(), this.refreshBundle()]);
            this.storageWarning = idbFailed;
            await this.countPending();
            this.flush();
            setInterval(() => this.flush(), 10_000);
            setInterval(() => this.refreshBundle(), 60_000);
            this.startCamera();
        },

        // ---- bundle -------------------------------------------------------
        async loadBundle() {
            const b = await store.get('bundle', cfg.eventSlug);
            if (b && !this.bundle) this.applyBundle(b); // don't overwrite a fresher network bundle
        },
        async refreshBundle() {
            if (!navigator.onLine) return;
            try {
                const r = await fetch(cfg.bundleUrl, { headers: { Accept: 'application/json' } });
                if (r.status === 401) { this.sessionExpired = true; return; }
                if (!r.ok) return;
                const b = await r.json();
                this.applyBundle(b);
                store.put('bundle', b, cfg.eventSlug).catch(() => {});
            } catch {}
        },
        applyBundle(b) {
            this.bundle = b;
            this.gates = b.gates ?? [];
            this._passIndex = new Map(b.passes.map((p) => [p.code, p]));
            this._winners = b.winners ?? {};
            // Rostered post wins; otherwise first entry gate.
            if (!this.gateId && cfg.shift?.gate_id && this.gates.some((g) => g.id === cfg.shift.gate_id && g.is_entry)) this.gateId = cfg.shift.gate_id;
            if (!this.gateId && this.gates.length) this.gateId = this.gates.find((g) => g.is_entry)?.id ?? '';
        },

        // ---- scanning -----------------------------------------------------
        async startCamera() {
            // Camera and WebCrypto only exist on HTTPS (or localhost). Say so instead of showing black.
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.cameraError = 'The camera needs a secure (https) address. Open the scanner from the event\'s real link, or type pass codes below.';
                return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                const v = this.$refs.video;
                v.srcObject = stream;
                await v.play();
                this.tick();
            } catch (e) {
                this.cameraError = 'Camera unavailable. Type the pass code below instead.';
            }
        },
        async tick() {
            const v = this.$refs.video;
            if (v.readyState === v.HAVE_ENOUGH_DATA) {
                let text = null;
                if ('BarcodeDetector' in window) {
                    try {
                        this._detector ??= new BarcodeDetector({ formats: ['qr_code'] });
                        const codes = await this._detector.detect(v);
                        text = codes[0]?.rawValue ?? null;
                    } catch {}
                } else {
                    const c = this.$refs.canvas;
                    c.width = v.videoWidth; c.height = v.videoHeight;
                    const ctx = c.getContext('2d', { willReadFrequently: true });
                    ctx.drawImage(v, 0, 0);
                    const img = ctx.getImageData(0, 0, c.width, c.height);
                    text = jsQR(img.data, img.width, img.height)?.data ?? null;
                }
                if (text) await this.handleToken(text);
            }
            requestAnimationFrame(() => this.tick());
        },
        async manual() {
            const code = this.manualCode.trim().toUpperCase();
            if (!code) return;
            this.manualCode = '';
            // Typed codes: the signature comes from the cached list (the phone holds no secret).
            const pass = this._passIndex.get(code);
            if (!pass) return this.showFlash('warn', 'Unknown code', this.bundle ? `${code} is not in the cached list` : 'Connect once to download the list');
            // Typed codes are the volunteer's own fallback, so they bypass strict mode: send a rotating token for now.
            await this.handleToken(this.bundle?.event?.strict_passes ? `EQ2.${code}.${currentSlot()}.${await rotatingMac(pass.sig, currentSlot())}` : `EQ1.${code}.${pass.sig}`);
        },
        async handleToken(raw) {
            // Debounce the same QR sitting in front of the camera.
            const now = Date.now();
            if (raw === this._lastToken && now - this._lastAt < 4000) return;
            this._lastToken = raw; this._lastAt = now;

            // Gatezo URLs are matched by path only: the printed host (localhost, LAN IP,
            // real domain) can differ from the one the scanner was opened on.
            const path = urlPath(raw);
            if (path) {
                if (this.mode === 'gate' && path.startsWith(cfg.gateSignPath)) return this.dutyAt(path.slice(cfg.gateSignPath.length).split(/[/?#]/)[0]);
                if (path.startsWith('/scan/g/')) return this.showFlash('warn', 'Sign for another event', 'This gate sign is not from this event');
                if (path.startsWith('/e/') && path.endsWith('/feedback')) return this.showFlash('warn', 'Feedback card', 'Attendees scan this with their camera app');
                if (path.startsWith('/e/')) return this.showFlash('warn', 'Registration poster', 'Attendees scan this with their camera app to get a pass');
                if (path.startsWith('/stall/')) return this.showFlash('warn', 'Stall card', 'Attendees scan this with their camera app');
                if (path.startsWith('/pass/')) return this.showFlash('warn', 'Pass link, not the pass', 'Ask them to open the link and show the QR on it');
            }

            const t = parseToken(raw);
            if (!t) return this.showFlash('bad', 'Not a pass', 'Wrong QR code');
            if (!this.bundle) return this.showFlash('bad', 'No data', 'Connect once to download the list');

            // Offline verification = compare with the signature cached for this code.
            // Unknown codes (registered after the download) are queued and verified by the server.
            const pass = this._passIndex.get(t.code);
            const strict = !!this.bundle.event?.strict_passes;
            if (t.v === 1) {
                if (strict) return this.showFlash('bad', 'Screenshot pass', 'This event uses live passes. Ask them to open their pass link.');
                if (pass && pass.sig !== t.sig) return this.showFlash('bad', 'Invalid pass', t.code);
            } else if (pass) {
                if (Math.abs(t.slot - currentSlot()) > SLOT_WINDOW) return this.showFlash('bad', 'Expired pass', 'Ask them to refresh their pass page');
                if ((await rotatingMac(pass.sig, t.slot)) !== t.mac) return this.showFlash('bad', 'Invalid pass', t.code);
            }
            if (this.mode === 'lead') return this.captureLead(raw, t.code, pass);
            // On stage right now? Show the claim bar; the check-in still records below.
            if (this.mode === 'gate' && this._winners?.[t.code]) this.winner = { code: t.code, name: pass?.name ?? t.code, prize: this._winners[t.code].prize, token: raw };
            if (!pass) return this.showFlash('warn', 'Unknown pass', `${t.code} · not in cached list, will sync`);

            const scan = {
                client_id: crypto.randomUUID(),
                token: raw,
                gate_id: this.gateId || null,
                direction: this.direction,
                scanned_at: new Date().toISOString(),
            };

            // Does this scan actually move the person? If not, ask the volunteer before recording.
            // A forwarded screenshot lands here: the pass is already inside and nobody scanned it out.
            const reentry = this.bundle.event?.allow_reentry;
            const blocked = this.direction === 'in' ? (pass.inside || (!reentry && pass.entered)) : !pass.inside;
            if (blocked) {
                const when = pass.last_at ? new Date(pass.last_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '';
                this.hold = {
                    title: this.direction === 'in' ? (pass.inside ? 'Already inside' : 'Already used · no re-entry') : 'Not inside',
                    name: pass.name,
                    detail: when ? `${pass.inside ? 'Checked in' : 'Last seen'} ${when}${pass.last_gate ? ' at ' + pass.last_gate : ''}` : 'Scanned before this list was downloaded',
                    scan, pass,
                };
                if (navigator.vibrate) navigator.vibrate([60, 40, 60, 40, 60]);
                return;
            }
            await this.record(scan, pass);
        },
        async record(scan, pass, decision = null) {
            scan = { ...scan }; // plain copy: a reactive proxy (from `hold`) cannot be stored in IndexedDB
            if (decision) scan.decision = decision;
            await store.put('queue', scan);
            this.pending++;
            // Update the cached state right away, so the next scan of the same pass (any phone
            // after sync, this phone immediately) sees the truth.
            if (decision !== 'turned_away') {
                pass.inside = scan.direction === 'in'; pass.entered = pass.entered || scan.direction === 'in';
                pass.last_at = scan.scanned_at; pass.last_gate = this.gates.find((g) => g.id === scan.gate_id)?.name ?? null;
            }
            const label = decision === 'turned_away' ? 'Turned away' : decision === 'let_in' ? 'Let in · flagged' : (scan.direction === 'in' ? 'Checked in' : 'Checked out');
            this.pushRecent({ ...scan, name: pass.name, code: parseToken(scan.token)?.code, status: decision === 'turned_away' ? 'turned_away' : 'queued' });
            this.showFlash(decision === 'turned_away' ? 'bad' : decision ? 'warn' : 'ok', pass.name, `${pass.is_vip ? 'VIP · ' : ''}${label}`);
            this.flush();
        },
        async decide(decision) {
            const h = this.hold; this.hold = null;
            if (h) await this.record(h.scan, h.pass, decision);
        },

        // ---- gate sign → duty ---------------------------------------------
        async dutyAt(code) {
            const gate = this.gates.find((g) => g.code.toUpperCase() === code.toUpperCase());
            if (!gate) return this.showFlash('bad', 'Unknown gate', code);
            if (gate.is_entry) this.gateId = gate.id;
            // Soft nudge only; never block. The organizer sees the mismatch on the board.
            const offRoster = cfg.shift?.gate_id && cfg.shift.gate_id !== gate.id;
            try {
                const r = await fetch(cfg.dutyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ gate_id: gate.id, status: 'on' }),
                });
                this.showFlash(r.ok && !offRoster ? 'ok' : 'warn', 'On duty', gate.name + (offRoster ? ` · you're rostered at ${cfg.shift.gate_name}` : '') + (r.ok ? '' : ' · will retry when online'));
            } catch {
                this.showFlash('warn', 'On duty (offline)', gate.name);
            }
        },

        // ---- draw winner claim --------------------------------------------------
        async claimWinner() {
            if (!this.winner) return;
            try {
                const r = await fetch(cfg.claimUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ token: this.winner.token }),
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok) { this.showFlash('ok', 'Claimed', `${this.winner.name} · ${d.prize}`); delete this._winners[this.winner.code]; }
                else this.showFlash('warn', 'Not claimed', d.status === 'not_a_winner' ? 'No longer on stage' : 'Try again');
            } catch { this.showFlash('bad', 'Network error', 'Try again'); }
            this.winner = null;
        },

        // ---- vendor lead capture -------------------------------------------
        async captureLead(token, code, pass) {
            if (pass && pass.opted_in === false) return this.showFlash('warn', 'Not opted in', `${pass.name} · ask them to allow contact on their pass`);
            if (!navigator.onLine) return this.showFlash('warn', 'Offline', 'Leads need a connection. Try again in a moment.');
            try {
                const r = await fetch(cfg.leadUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ token }),
                });
                const d = await r.json().catch(() => ({}));
                const name = d.lead?.name ?? d.name ?? pass?.name ?? code;
                this.pushRecent({ client_id: crypto.randomUUID(), name, code, status: d.status ?? 'error' });
                if (d.status === 'ok') { this.showFlash('ok', 'Lead saved', name); if (window.Alpine) Alpine.store('leadCount', (Alpine.store('leadCount') ?? this.recent.length) + 1); }
                else if (d.status === 'already_captured') this.showFlash('warn', 'Already saved', name);
                else if (d.status === 'not_opted_in') this.showFlash('warn', 'Not opted in', `${name} · ask them to allow contact on their pass`);
                else this.showFlash('bad', 'Not saved', d.status ?? r.status);
            } catch {
                this.showFlash('bad', 'Network error', 'Try again');
            }
        },

        // ---- sync ---------------------------------------------------------
        async countPending() {
            this.pending = await store.count('queue');
        },
        async flush() {
            if (this.mode !== 'gate' || this._flushing || !navigator.onLine) return;
            this._flushing = true;
            try {
                const scans = await store.getAll('queue');
                if (!scans.length) return;
                const r = await fetch(cfg.syncUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ scans: scans.slice(0, 200) }),
                });
                // Session gone (401) or CSRF stale (419): keep the queue, ask the volunteer to rejoin.
                // The queue is keyed by event, so it drains as soon as they're back.
                if (r.status === 401 || r.status === 419) { this.sessionExpired = true; return; }
                if (!r.ok) return;
                this.sessionExpired = false;
                const { results } = await r.json();
                for (const res of results) {
                    await store.del('queue', res.client_id);
                    const row = this.recent.find((x) => x.client_id === res.client_id);
                    if (row) row.status = res.status;
                }
                this.refreshBundle(); // pull the state the other phones produced
                await this.countPending();
            } catch {} finally {
                this._flushing = false;
            }
        },

        // ---- ui -----------------------------------------------------------
        pushRecent(row) {
            this.recent.unshift(row);
            if (this.recent.length > 30) this.recent.pop();
        },
        showFlash(kind, title, detail) {
            this.flash = { kind, title, detail };
            if (navigator.vibrate) navigator.vibrate(kind === 'ok' ? 80 : [60, 40, 60]);
            clearTimeout(this._flashT);
            this._flashT = setTimeout(() => (this.flash = null), 1500);
        },
    };
}
