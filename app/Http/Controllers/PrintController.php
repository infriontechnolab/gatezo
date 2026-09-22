<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Qr;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate as Authz;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Organizer-only pages meant for the browser's Print / Save as PDF: the print kit
 * (every QR you stick on something) and the post-event report. Plus the attendee CSV.
 */
class PrintController extends Controller
{
    public function kit(Event $event): View
    {
        Authz::authorize('manage', $event);

        $event->load(['gates', 'stalls']);

        return view('print.kit', [
            'event' => $event,
            'posterQr' => Qr::svg(route('event.show', $event), 600),
            'feedbackQr' => Qr::svg(route('event.feedback', $event), 400),
            'gateQrs' => $event->gates->mapWithKeys(fn ($g) => [$g->id => Qr::svg($g->signUrl(), 400)]),
            'stallQrs' => $event->stalls->mapWithKeys(fn ($s) => [$s->id => Qr::svg(route('stall.show', $s), 300)]),
        ]);
    }

    /**
     * Every QR for this event as a bare code (no sheet, no caption), for organizers who
     * design their own poster. Keyed by a slug that is also the file name.
     *
     * @return array<string, array{label: string, where: string, url: string}>
     */
    public static function codes(Event $event): array
    {
        $codes = [
            'register' => ['label' => 'Registration', 'where' => 'Poster, WhatsApp image, banner: scan → get a pass', 'url' => route('event.show', $event)],
            'feedback' => ['label' => 'Feedback', 'where' => 'Exit card: scan → star rating', 'url' => route('event.feedback', $event)],
        ];
        foreach ($event->gates()->orderBy('code')->get() as $gate) {
            $codes['gate-'.Str::slug($gate->code)] = ['label' => "Gate sign · {$gate->name}", 'where' => 'Where volunteers arrive: scan → on duty here', 'url' => $gate->signUrl()];
        }
        foreach ($event->stalls()->orderBy('name')->get() as $stall) {
            $codes['stall-'.Str::slug($stall->name).'-'.$stall->code] = ['label' => "Stall · {$stall->name}", 'where' => 'Stall counter: scan → menu & offers', 'url' => route('stall.show', $stall)];
        }

        return $codes;
    }

    /** One code as PNG (1024 px, quiet zone) or SVG. */
    public function qr(Event $event, string $key, string $format): Response
    {
        Authz::authorize('manage', $event);
        $code = self::codes($event)[$key] ?? abort(404);
        $file = Str::slug($event->name).'-'.$key.'.'.$format;

        return match ($format) {
            'png' => response(Qr::png($code['url']), 200, ['Content-Type' => 'image/png', 'Content-Disposition' => "attachment; filename=\"{$file}\""]),
            'svg' => response(Qr::svg($code['url'], 1024), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => "attachment; filename=\"{$file}\""]),
            default => abort(404),
        };
    }

    /** All codes, PNG + SVG, with a README saying what each one is and where it points. */
    public function qrZip(Event $event): BinaryFileResponse
    {
        Authz::authorize('manage', $event);
        $codes = self::codes($event);

        $path = tempnam(sys_get_temp_dir(), 'qr');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $readme = "QR codes for {$event->name}\nGenerated ".now()->format('j M Y, g:i A')."\n\nKeep at least the white border around each code, and print it no smaller than 2 cm.\n\n";
        foreach ($codes as $key => $code) {
            $zip->addFromString("png/{$key}.png", Qr::png($code['url']));
            $zip->addFromString("svg/{$key}.svg", Qr::svg($code['url'], 1024));
            $readme .= "{$key}\n  {$code['label']}: {$code['where']}\n  {$code['url']}\n\n";
        }
        $zip->addFromString('README.txt', $readme);
        $zip->close();

        return response()->download($path, Str::slug($event->name).'-qr-codes.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function report(Event $event): View
    {
        Authz::authorize('manage', $event);

        $ins = $event->checkins()->where('direction', 'in');

        // Arrivals per 15-minute bucket → peak time + a simple bar chart.
        $buckets = (clone $ins)
            ->selectRaw("DATE_FORMAT(scanned_at, '%Y-%m-%d %H:') as h, FLOOR(MINUTE(scanned_at)/15)*15 as m, COUNT(*) as n")
            ->groupBy('h', 'm')->orderBy('h')->orderBy('m')->get()
            ->map(fn ($r) => ['at' => $r->h.str_pad((string) $r->m, 2, '0', STR_PAD_LEFT), 'n' => (int) $r->n]);
        $peak = $buckets->sortByDesc('n')->first();

        $ratings = $event->feedback()->select('rating', DB::raw('count(*) as n'))->groupBy('rating')->pluck('n', 'rating');

        return view('print.report', [
            'event' => $event,
            'registered' => $event->attendees()->count(),
            'checkedIn' => (clone $ins)->distinct('pass_id')->count('pass_id'),
            'totalScans' => (clone $ins)->count(),
            'duplicates' => $event->checkins()->where('duplicate_flag', true)->count(),
            'walkups' => $event->attendees()->where('source', 'walkup')->count(),
            'peak' => $peak,
            'buckets' => $buckets,
            'gates' => $event->gates()->where('is_entry', true)->withCount(['checkins as ins' => fn ($q) => $q->where('direction', 'in')])->get(),
            'volunteers' => $event->dutyLogs()->distinct('volunteer_id')->count('volunteer_id'),
            'shifts' => $event->shifts()->with('gate')->orderBy('starts_at')->get()->map(fn ($s) => [
                'name' => $s->volunteer_name, 'post' => $s->gate?->name ?? $s->label ?? 'Anywhere',
                'window' => $s->starts_at ? $s->starts_at->format('g:i A').($s->ends_at ? ' to '.$s->ends_at->format('g:i A') : '') : '—',
                'status' => $s->status(),
            ]),
            'stalls' => $event->stalls()->withCount('leads')->orderByDesc('view_count')->get(),
            'feedbackCount' => $ratings->sum(),
            'feedbackAvg' => $ratings->sum() ? round(collect($ratings)->map(fn ($n, $r) => $n * $r)->sum() / $ratings->sum(), 2) : null,
            'ratings' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($r) => [$r => (int) ($ratings[$r] ?? 0)]),
            'comments' => $event->feedback()->whereNotNull('comment')->where('comment', '!=', '')->latest()->limit(15)->get(),
        ]);
    }

    public function attendeesCsv(Event $event): StreamedResponse
    {
        Authz::authorize('manage', $event);

        $rows = $event->attendees()->with(['pass' => fn ($q) => $q->withMin('checkins as first_in', 'scanned_at')])->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Email', 'Ticket', 'VIP', 'Source', 'Pass', 'Checked in at', 'Registered at']);
            foreach ($rows as $a) {
                fputcsv($out, [$a->name, $a->phone, $a->email, $a->ticket_type, $a->is_vip ? 'yes' : '', $a->source, $a->pass?->code, $a->pass?->first_in, $a->created_at]);
            }
            fclose($out);
        }, $event->slug.'-attendees.csv', ['Content-Type' => 'text/csv']);
    }
}
