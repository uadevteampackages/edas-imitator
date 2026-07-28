<?php

namespace Uadevteampackages\Imitator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Uadevteampackages\Imitator\Imitator;

class EnsureLocalEnvironment
{
    public function __construct(private Imitator $imitator) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->imitator->enabled()) {
            abort(404);
        }

        return $next($request);
    }
}
