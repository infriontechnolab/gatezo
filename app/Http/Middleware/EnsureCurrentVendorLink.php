<?php

namespace App\Http\Middleware;

use App\Models\Stall;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after `signed`. The signature proves the link was issued by us; this checks
 * it's the *current* one, so "Regenerate link" in the panel kills forwarded copies.
 */
class EnsureCurrentVendorLink
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Stall|null $stall */
        $stall = $request->route('stall');

        if (! $stall instanceof Stall || (int) $request->query('v') !== (int) $stall->link_version) {
            abort(403, 'This vendor link is no longer valid. Ask the organizer for a new one.');
        }

        return $next($request);
    }
}
