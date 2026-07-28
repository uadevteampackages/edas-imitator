<?php

namespace Uadevteampackages\Imitator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class EnsureAuthenticated
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard(config('auth.defaults.guard', 'web'))->check()) {
            return $next($request);
        }

        $redirectTo = config('imitator.redirect_guests_to');

        if (filled($redirectTo)) {
            return redirect()->guest($redirectTo);
        }

        try {
            return redirect()->guest(route('login'));
        } catch (RouteNotFoundException) {
            abort(401, 'Unauthenticated.');
        }
    }
}
