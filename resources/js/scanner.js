/**
 * Scanner component, two modes:
 *   mode:'gate'  volunteer check-in (default). Also recognises printed gate-sign URLs → "on duty here".
 *   mode:'lead'  vendor lead capture: scanning a pass posts to leadUrl; requires attendee opt-in.
 *
 * Designed for "the venue Wi-Fi died":
 *   - bundle (event secret + pass list + gates) cached in IndexedDB
 *   - every scan verified locally: HMAC signature via WebCrypto, then pass lookup
 *   - scans appended to an IndexedDB queue and flushed whenever we're online
 *   - server flags cross-gate duplicates at sync; we never block at the gate
 */
import jsQR from 'jsqr';
import { openDB } from 'idb';

const DB_NAME = 'eventqr-scanner';
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';

async function db() {
    return openDB(DB_NAME, 1, {
        upgrade(d) {
            d.createObjectStore('bundle');
            d.createObjectStore('queue', { keyPath: 'client_id' });
        },
    });
}

async function hmacHex16(secret, message) {
    const enc = new TextEncoder();
    const key = await crypto.subtle.importKey('raw', enc.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
    const sig = await crypto.subtle.sign('HMAC', key, enc.encode(message));
    return [...new Uint8Array(sig)].map((b) => b.toString(16).padStart(2, '0')).join('').slice(0, 16);
}

// Mirrors App\Services\PassToken: "EQ1.<code>.<sig16>"
function parseToken(raw) {
    const parts = String(raw).trim().split('.');
    if (parts.length !== 3 || parts[0] !== 'EQ1') return null;
    return { code: parts[1].toUpperCase(), sig: parts[2].toLowerCase() };
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
        _passIndex: new Map(),
        _lastToken: null,
        _lastAt: 0,
        _flushing: false,

        mode: cfg.mode ?? 'gate',

        async init() {
            if (cfg.duty) this.showFlash('ok', 'On duty', cfg.duty);
            window.addEventListener('online', () => { this.online = true; this.refreshBundle(); this.flush(); });
            window.addEventListener('offline', () => (this.online = false));
            await this.loadBundle();
            await this.countPending();
            this.refreshBundle();
            this.flush();
            setInterval(() => this.flush(), 10_000);
            this.startCamera();
        },

        // ---- bundle -------------------------------------------------------
        async loadBundle() {
            const d = await db();
            const b = await d.get('bundle', cfg.eventSlug);
            if (b) this.applyBundle(b);
        },
        async refreshBundle() {
            if (!navigator.onLine) return;
            try {
                const r = await fetch(cfg.bundleUrl, { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const b = await r.json();
                (await db()).put('bundle', b, cfg.eventSlug);
                this.applyBundle(b);
            } catch {}
        },
        applyBundle(b) {
            this.bundle = b;
            this.gates = b.gates ?? [];
            this._passIndex = new Map(b.passes.map((p) => [p.code, p]));
            if (!this.gateId && this.gates.length) this.gateId = this.gates.find((g) => g.is_entry)?.id ?? '';
        },

        // ---- scanning -----------------------------------------------------
        async startCamera() {
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
            // Typed codes have no signature; sign it ourselves with the cached secret.
            if (!this.bundle) return this.showFlash('bad', 'No data', 'Connect once to download the list');
            await this.handleToken(`EQ1.${code}.${await hmacHex16(this.bundle.pass_secret, code)}`);
        },
        async handleToken(raw) {
            // Debounce the same QR sitting in front of the camera.
            const now = Date.now();
            if (raw === this._lastToken && now - this._lastAt < 4000) return;
            this._lastToken = raw; this._lastAt = now;

            // Printed gate/zone sign → "I'm on duty here"
            if (this.mode === 'gate' && cfg.gateSignPrefix && raw.startsWith(cfg.gateSignPrefix)) {
                return this.dutyAt(raw.slice(cfg.gateSignPrefix.length).split(/[/?#]/)[0]);
            }

            const t = parseToken(raw);
            if (!t) return this.showFlash('bad', 'Not a pass', 'Wrong QR code');
            if (!this.bundle) return this.showFlash('bad', 'No data', 'Connect once to download the list');

            const expected = await hmacHex16(this.bundle.pass_secret, t.code);
            if (expected !== t.sig) return this.showFlash('bad', 'Invalid pass', t.code);

            const pass = this._passIndex.get(t.code);
            if (this.mode === 'lead') return this.captureLead(raw, t.code, pass);
            if (!pass) return this.showFlash('warn', 'Unknown pass', `${t.code} · not in cached list, will sync`);

            const scan = {
                client_id: crypto.randomUUID(),
                token: raw,
                gate_id: this.gateId || null,
                direction: this.direction,
                scanned_at: new Date().toISOString(),
            };
            (await db()).put('queue', scan);
            this.pending++;
            this.pushRecent({ ...scan, name: pass.name, code: t.code, status: 'queued' });
            this.showFlash('ok', pass.name, `${pass.is_vip ? 'VIP · ' : ''}${this.direction === 'in' ? 'Checked in' : 'Checked out'}`);
            this.flush();
        },

        // ---- gate sign → duty ---------------------------------------------
        async dutyAt(code) {
            const gate = this.gates.find((g) => g.code.toUpperCase() === code.toUpperCase());
            if (!gate) return this.showFlash('bad', 'Unknown gate', code);
            if (gate.is_entry) this.gateId = gate.id;
            try {
                const r = await fetch(cfg.dutyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ gate_id: gate.id, status: 'on' }),
                });
                this.showFlash(r.ok ? 'ok' : 'warn', 'On duty', gate.name + (r.ok ? '' : ' · will retry when online'));
            } catch {
                this.showFlash('warn', 'On duty (offline)', gate.name);
            }
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
            this.pending = await (await db()).count('queue');
        },
        async flush() {
            if (this.mode !== 'gate' || this._flushing || !navigator.onLine) return;
            this._flushing = true;
            try {
                const d = await db();
                const scans = await d.getAll('queue');
                if (!scans.length) return;
                const r = await fetch(cfg.syncUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ scans: scans.slice(0, 200) }),
                });
                if (!r.ok) return;
                const { results } = await r.json();
                const tx = d.transaction('queue', 'readwrite');
                for (const res of results) {
                    await tx.store.delete(res.client_id);
                    const row = this.recent.find((x) => x.client_id === res.client_id);
                    if (row) row.status = res.status;
                }
                await tx.done;
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
