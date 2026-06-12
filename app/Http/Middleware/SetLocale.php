<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'es'];

        $locale = session('locale');

        if (! in_array($locale, $supported, true)) {
            $locale = $request->cookie('locale');

            if (in_array($locale, $supported, true)) {
                session(['locale' => $locale]); // re-hydrate after session loss (e.g. logout)
            }
        }

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale'); // 'en' — the default for new visitors
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
