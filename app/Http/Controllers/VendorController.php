<?php

namespace App\Http\Controllers;

use App\Models\Stall;
use App\Services\PassToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor side of a stall. No account: the organizer hands the vendor a signed link.
 * The page shows scan count + leads and embeds the scanner in "lead" mode: scanning an
 * attendee's pass captures a lead only if that attendee opted in on their pass page.
 */
class VendorController extends Controller
{
    public function show(Stall $stall): View
    {
        $event = $stall->event;

        return view('vendor.show', [
            'stall' => $stall,
            'event' => $event,
            'leads' => $stall->leads()->with('attendee')->latest()->get(),
            'bundleUrl' => URL::signedRoute('vendor.bundle', $stall),
            'leadUrl' => URL::signedRoute('vendor.lead', $stall),
            'csvUrl' => URL::signedRoute('vendor.leads.csv', $stall),
        ]);
    }

    /** Same shape as the volunteer bundle so scanner.js can verify passes offline. */
    public function bundle(Stall $stall): JsonResponse
    {
        $event = $stall->event;

        return response()->json([
            'event' => $event->only(['slug', 'name', 'accent_hex']),
            'pass_secret' => $event->pass_secret,
            'gates' => [],
            'passes' => $event->passes()->with('attendee:id,name,share_contact')->where('revoked', false)->get()
                ->map(fn ($p) => ['code' => $p->code, 'name' => $p->attendee->name, 'opted_in' => $p->attendee->share_contact]),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function lead(Request $request, Stall $stall): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:64'], 'note' => ['nullable', 'string', 'max:500']]);
        $event = $stall->event;

        if (! PassToken::verify($data['token'], $event)) {
            return response()->json(['status' => 'invalid_signature'], 422);
        }
        $pass = $event->passes()->where('code', PassToken::parse($data['token'])['code'])->with('attendee')->first();
        if (! $pass) {
            return response()->json(['status' => 'unknown_pass'], 404);
        }
        if (! $pass->attendee->share_contact) {
            return response()->json(['status' => 'not_opted_in', 'name' => $pass->attendee->name], 403);
        }

        $lead = $stall->leads()->firstOrCreate(['attendee_id' => $pass->attendee_id], ['note' => $data['note'] ?? null]);

        return response()->json([
            'status' => $lead->wasRecentlyCreated ? 'ok' : 'already_captured',
            'lead' => ['name' => $pass->attendee->name, 'phone' => $pass->attendee->phone, 'email' => $pass->attendee->email],
        ]);
    }

    public function leadsCsv(Stall $stall): StreamedResponse
    {
        $rows = $stall->leads()->with('attendee')->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Email', 'Note', 'Captured at']);
            foreach ($rows as $l) {
                fputcsv($out, [$l->attendee->name, $l->attendee->phone, $l->attendee->email, $l->note, $l->created_at]);
            }
            fclose($out);
        }, str($stall->name)->slug().'-leads.csv', ['Content-Type' => 'text/csv']);
    }
}
