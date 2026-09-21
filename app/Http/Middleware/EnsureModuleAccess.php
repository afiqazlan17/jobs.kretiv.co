<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Route guard for a Kretiv OS module — `->middleware('module:jobs')`.
// Runs after `auth`; a signed-in user without the module gets a 403.
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($request->user()?->canAccess($module), 403, 'You don’t have access to this module. Ask BOD to enable it for you.');

        return $next($request);
    }
}
