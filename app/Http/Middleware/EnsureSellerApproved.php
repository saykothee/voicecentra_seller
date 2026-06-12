<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->isSeller()) {
            return redirect()->route('dashboard'); // non-sellers (e.g. admins) routed to their home
        }

        if (! $user->isApproved()) {
            return redirect()->route('pending');
        }

        return $next($request);
    }
}
