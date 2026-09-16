<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Qr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate as Authz;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
