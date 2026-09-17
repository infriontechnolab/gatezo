/**
 * Gatezo landing interactions. Vanilla, no dependencies beyond what the page has.
 * Every animation replays the same story: a QR is scanned → Gatezo records it → a number moves.
 */
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const rand = (a) => a[Math.floor(Math.random() * a.length)];
const FIRST = ['Aarti', 'Bhavesh', 'Chirag', 'Dipti', 'Esha', 'Falguni', 'Gaurav', 'Hetal', 'Ishaan', 'Jinal', 'Kunal', 'Leena', 'Meera', 'Nirav', 'Priya', 'Rahul', 'Riya', 'Sagar', 'Sneha', 'Tejas', 'Urvi', 'Yash'];
const LAST = ['S.', 'P.', 'M.', 'J.', 'T.', 'D.', 'R.', 'V.', 'B.', 'K.'];
const name = () => `${rand(FIRST)} ${rand(LAST)}`;
const onEnter = (el, cb, threshold = 0.35) => {
    if (!el) return;
    if (!('IntersectionObserver' in window)) return cb();
    const io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { cb(); io.disconnect(); } }), { threshold });
    io.observe(el);
};
const countTo = (el, target, ms = 1200) => {
    if (!el) return;
    if (reduced) { el.textContent = target.toLocaleString('en-IN'); return; }
    const t0 = performance.now();
    const step = (t) => { const p = Math.min(1, (t - t0) / ms); el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('en-IN'); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
};

/* ---- Hero: scan loop. QR appears → scan line → "Checked in" → counter +1 ------------- */
(() => {
    const phone = $('#hero-phone'); if (!phone) return;
    const who = $('#hero-who'), counter = $('#hero-count'), chip = $('#hero-chip');
    let inside = 1099;
    counter.textContent = inside.toLocaleString('en-IN');
    if (reduced) { phone.classList.add('checked'); return; }
    const cycle = () => {
        phone.classList.remove('checked'); phone.classList.add('scanning');
        setTimeout(() => {
            phone.classList.remove('scanning'); who.textContent = name(); phone.classList.add('checked');
            inside += 1; counter.textContent = inside.toLocaleString('en-IN'); chip.classList.remove('bump'); void chip.offsetWidth; chip.classList.add('bump');
        }, 1150);
    };
    setTimeout(cycle, 900); setInterval(cycle, 4200);
})();

/* ---- Ticker: new activity slides in, oldest drops off -------------------------------- */
(() => {
    const track = $('#ticker'); if (!track) return;
    const gates = ['Main Gate', 'Tower B Gate', 'VIP Entry'];
    const stalls = ['Chai Point', 'Kesar Kulfi', 'Pav Bhaji Junction', 'Dandiya Sticks'];
    const kinds = [
        () => [`<b>${name()}</b> checked in at ${rand(gates)}`, '#E8604C'],
        () => [`<b>${name()}</b> checked in at ${rand(gates)}`, '#E8604C'],
        () => [`New scan at <b>${rand(stalls)}</b>`, '#6B2D5C'],
        () => [`<b>${rand(FIRST)}</b> is on duty at ${rand(gates)}`, '#6B2D5C'],
        () => [`Feedback: <b>${rand(['5', '4', '5', '4', '3'])} ★</b> "${rand(['Great music!', 'Entry was quick', 'Parking again…', 'Loved the food court'])}"`, '#F5A623'],
        () => [`<b>${name()}</b> registered from the poster`, '#E8604C'],
    ];
    const add = () => {
        const [html, color] = rand(kinds)();
        const li = document.createElement('span'); li.className = 'ticker-item'; li.innerHTML = `<i class="k" style="background:${color}"></i><span>${html}</span><span class="text-neutral-400">· just now</span>`;
        track.prepend(li);
        $$('.ticker-item', track).forEach((it, i) => { const t = $('span:last-child', it); if (t) t.textContent = i === 0 ? '· just now' : `· ${i * 3 + Math.floor(Math.random() * 3)} sec ago`; });
        while (track.children.length > 8) track.lastElementChild.remove();
    };
    for (let i = 0; i < 6; i++) add();
    if (!reduced) setInterval(add, 2800);
})();

/* ---- Chaos → Gatezo: pieces converge into one screen when the section is seen ---------- */
onEnter($('#chaos'), () => $('#chaos').classList.add('tidy'), 0.45);

/* ---- Event map: scans are born at markers and fly into the live counter ----------------- */
(() => {
    const wrap = $('#map-wrap'); if (!wrap) return;
    const svg = $('svg', wrap), pill = $('#map-inside'), tip = $('#map-tip');
    const counts = { G1: 886, G2: 317, G3: 45 };
    let inside = 1099, active = false;
    const badges = Object.fromEntries($$('.gate-badge', wrap).map((b) => [b.dataset.gate, b]));
    Object.entries(counts).forEach(([g, n]) => { if (badges[g]) $('.num', badges[g]).textContent = n; });
    const pos = (el) => { // marker centre in wrapper pixels
        const r = el.getBoundingClientRect(), w = wrap.getBoundingClientRect();
        return { x: r.left - w.left + r.width / 2, y: r.top - w.top + r.height / 2 };
    };
    const markers = $$('.marker', svg);
    markers.forEach((m) => {
        const show = () => { const p = pos(m); tip.style.left = p.x + 'px'; tip.style.top = p.y + 'px'; tip.textContent = m.dataset.tip + (counts[m.dataset.gate] ? ` · ${counts[m.dataset.gate]} in` : ''); tip.classList.add('on'); };
        m.addEventListener('mouseenter', show); m.addEventListener('focus', show);
        m.addEventListener('mouseleave', () => tip.classList.remove('on')); m.addEventListener('blur', () => tip.classList.remove('on'));
        m.addEventListener('click', () => spawn(m));
    });
    const spawn = (m) => {
        const from = pos(m), to = pos(pill); to.x = to.x - 40; to.y += 8;
        const dot = document.createElement('i'); dot.className = 'scan-dot' + (m.classList.contains('vol') ? ' vol' : '');
        dot.style.left = from.x + 'px'; dot.style.top = from.y + 'px'; wrap.appendChild(dot);
        requestAnimationFrame(() => requestAnimationFrame(() => { dot.style.left = to.x + 'px'; dot.style.top = to.y + 'px'; }));
        setTimeout(() => {
            dot.style.opacity = '0';
            if (m.dataset.gate && counts[m.dataset.gate] !== undefined) {
                counts[m.dataset.gate]++; inside++;
                $('.num', badges[m.dataset.gate]).textContent = counts[m.dataset.gate];
                $('.num', pill).textContent = inside.toLocaleString('en-IN'); pill.classList.remove('bump'); void pill.offsetWidth; pill.classList.add('bump');
            }
            setTimeout(() => dot.remove(), 300);
        }, 1100);
    };
    onEnter(wrap, () => {
        active = true;
        if (reduced) return;
        const loop = () => { if (!document.hidden) spawn(rand(markers.filter((m) => m.dataset.gate || Math.random() < 0.35))); setTimeout(loop, 700 + Math.random() * 900); };
        loop();
    }, 0.3);
})();

/* ---- How it works: vertical scroll drives the horizontal track (desktop) ----------------- */
(() => {
    const story = $('.story'), track = $('#story-track'), bar = $('#story-bar'); if (!story || !track) return;
    const steps = $$('.story-step', track);
    const update = () => {
        if (innerWidth < 1024 || reduced) { track.style.transform = ''; steps.forEach((s) => s.classList.add('in')); return; }
        const r = story.getBoundingClientRect();
        const total = r.height - innerHeight;
        const p = Math.min(1, Math.max(0, -r.top / total));
        const max = track.scrollWidth - track.parentElement.clientWidth + 48;
        track.style.transform = `translateX(${-p * max}px)`;
        if (bar) bar.style.width = (p * 100) + '%';
        steps.forEach((s, i) => s.classList.toggle('in', p >= i / steps.length - 0.05));
    };
    addEventListener('scroll', update, { passive: true }); addEventListener('resize', update); update();
})();

/* ---- Bento: numbers count, bars grow, lines draw, once, when seen ------------------------ */
(() => {
    const bento = $('#bento'); if (!bento) return;
    onEnter(bento, () => {
        bento.classList.add('in');
        $$('[data-count]', bento).forEach((el) => countTo(el, +el.dataset.count));
        $$('.bars i', bento).forEach((b) => (b.style.height = b.dataset.h + '%'));
        $$('.hbar i', bento).forEach((b) => (b.style.width = b.dataset.w + '%'));
        const arc = $('.gauge .arc', bento); if (arc) { const len = arc.getTotalLength(); arc.style.strokeDasharray = `${len * (+arc.dataset.pct / 100)} ${len}`; }
        $$('.reveal', bento).forEach((el, i) => setTimeout(() => el.classList.add('in'), i * 80));
    }, 0.25);
})();

/* ---- Principles: tap toggles on touch (hover does it on pointer devices) ------------------ */
$$('.principle').forEach((p) => p.addEventListener('click', () => { if (matchMedia('(hover: none)').matches) p.classList.toggle('on'); }));

/* ---- Use-case scenes: hovering changes the data ------------------------------------------ */
$$('.scene').forEach((s) => {
    const n = $('.n', s); if (!n) return;
    const base = +n.dataset.base, hot = +n.dataset.hot;
    n.textContent = base.toLocaleString('en-IN');
    s.addEventListener('mouseenter', () => countTo(n, hot, 500)); s.addEventListener('mouseleave', () => countTo(n, base, 500));
});

/* ---- Magnetic CTAs: pointer devices only, tiny pull, never on touch ------------------------ */
if (matchMedia('(hover: hover) and (pointer: fine)').matches && !reduced) {
    $$('.btn-coral, .btn-white').forEach((b) => {
        b.addEventListener('mousemove', (e) => { const r = b.getBoundingClientRect(); const dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2); b.style.transform = `translate(${dx * 0.12}px, ${dy * 0.18}px)`; });
        b.addEventListener('mouseleave', () => (b.style.transform = ''));
    });
}
