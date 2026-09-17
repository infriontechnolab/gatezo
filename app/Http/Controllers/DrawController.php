<?php

namespace App\Http\Controllers;

use App\Models\Draw;
use App\Models\Event;
use App\Services\DrawEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/** Presenter screen (signed, read-only) and public results. */
class DrawController extends Controller
{
    public function stage(Event $event, Draw $draw): View
    {
        abort_unless($draw->event_id === $event->id, 404);

        return view('public.draw-stage', [
            'event' => $event,
            'draw' => $draw,
            'state' => DrawEngine::publicState($draw),
            'jsonUrl' => URL::signedRoute('draw.stage.json', [$event, $draw]),
        ]);
    }

    public function stageJson(Event $event, Draw $draw): JsonResponse
    {
        abort_unless($draw->event_id === $event->id, 404);

        return response()->json(DrawEngine::publicState($draw));
    }

    /** Every published draw for the event, with proof. */
    public function results(Event $event): View
    {
        $draws = $event->draws()->where('publish_results', true)->where('status', '!=', 'draft')
            ->with(['prizes', 'winners.prize', 'winners.pass.attendee'])->orderBy('run_at')->get();

        return view('public.draw-results', ['event' => $event, 'draws' => $draws]);
    }
}
