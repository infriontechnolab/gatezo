<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · {{ $event->name }}</title>
    <style>
        /* Print-first. Every .sheet is one A4 page. Black on white so a ₹5/page shop printer is enough. */
        :root { --accent: {{ $event->accent_hex }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; background: #e5e5e5; }
        .toolbar { position: sticky; top: 0; background: #111; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; font-size: 14px; }
        .toolbar button { background: var(--accent); color: #fff; border: 0; padding: 8px 14px; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 18mm; page-break-after: always; display: flex; flex-direction: column; }
        .sheet:last-child { page-break-after: auto; }
        .sheet.card-grid { display: grid; grid-template-columns: 1fr 1fr; grid-auto-rows: 1fr; gap: 8mm; }
        .card { border: 1.5px dashed #999; border-radius: 6mm; padding: 8mm; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .qr svg { width: 100%; height: auto; }
        .kicker { text-transform: uppercase; letter-spacing: .12em; font-size: 11px; color: #666; font-weight: 600; }
        .title { font-weight: 800; line-height: 1.05; margin: 4mm 0; }
        .code { font-family: ui-monospace, Menlo, monospace; font-size: 22px; letter-spacing: .2em; margin-top: 3mm; }
        .hint { color: #555; font-size: 13px; margin-top: 2mm; }
        .stripe { height: 6mm; background: var(--accent); border-radius: 3mm; }
        .brand { height: 7mm; width: auto; opacity: .85; }
        .stripe.volunteer { background: repeating-linear-gradient(45deg, #111 0 8px, #fff 8px 16px); }
        .write { border-bottom: 1.5px solid #999; height: 10mm; width: 100%; margin-top: 4mm; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #ddd; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #666; }
        .stat { border: 1px solid #ddd; border-radius: 6px; padding: 10px 12px; }
        .stat b { display: block; font-size: 26px; }
        .stat span { font-size: 12px; color: #666; }
        .bars { display: flex; align-items: flex-end; gap: 2px; height: 60mm; border-bottom: 1px solid #999; }
        .bars div { flex: 1; background: var(--accent); min-width: 2px; }
        h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .1em; color: #666; margin: 8mm 0 3mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: auto; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="print()">Print / Save as PDF</button>
        <span>{{ $title }} · {{ $event->name }}</span>
        <span style="margin-left:auto;opacity:.7">Tip: in the print dialog turn on "Background graphics".</span>
    </div>
    @yield('content')
</body>
</html>
