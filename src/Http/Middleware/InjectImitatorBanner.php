<?php

namespace Uadevteampackages\Imitator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Uadevteampackages\Imitator\Imitator;

class InjectImitatorBanner
{
    public function __construct(private Imitator $imitator) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('imitator.inject_banner', true)) {
            return $response;
        }

        if (! $this->imitator->enabled() || ! $this->imitator->active()) {
            return $response;
        }

        if (! $this->isHtmlResponse($response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $banner = view('imitator::banner', [
            'status' => $this->imitator->status(),
        ])->render();

        $updated = preg_replace('/(<body\b[^>]*>)/i', '$1'.$banner, $content, 1);

        if (is_string($updated)) {
            $response->setContent($updated);
        }

        return $response;
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }
}
