<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale)
    {
        if (in_array($locale, ['en', 'es'], true)) {
            session(['locale' => $locale]);
            cookie()->queue(cookie()->forever('locale', $locale));
        }

        return redirect()->back(fallback: route('landing'));
    }
}
