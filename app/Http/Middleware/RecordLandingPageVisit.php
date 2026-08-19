<?php

namespace App\Http\Middleware;

use App\Services\VisitTrackingService;
use App\Support\Enums\VisitSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordLandingPageVisit
{
    public function __construct(
        private readonly VisitTrackingService $trackingService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        try {
            $session = $request->session();
            if ($session->get('analytics_landing_visit_recorded') === true) {
                return $response;
            }

            $sessionId = $session->getId();
            if ($sessionId !== '') {
                $this->trackingService->record(VisitSource::LandingPage, $sessionId);
                $session->put('analytics_landing_visit_recorded', true);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return false;
        }

        if ($this->isPrefetchRequest($request)) {
            return false;
        }

        $userAgent = (string) $request->userAgent();

        return $userAgent === ''
            || preg_match(
                '/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|telegrambot|whatsapp|curl|wget/i',
                $userAgent,
            ) !== 1;
    }

    private function isPrefetchRequest(Request $request): bool
    {
        foreach (['Purpose', 'Sec-Purpose'] as $header) {
            if (str_contains(strtolower((string) $request->header($header)), 'prefetch')) {
                return true;
            }
        }

        return false;
    }
}
