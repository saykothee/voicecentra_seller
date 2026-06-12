<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SellerDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $paid = $user->commissionPayouts()->where('status', 'paid');

        return view('seller.dashboard', [
            'user' => $user,
            'totalEarnedCents' => (int) (clone $paid)->sum('amount_cents'),
            'earned30Cents' => (int) (clone $paid)->where('created_at', '>=', now()->subDays(30))->sum('amount_cents'),
            'pendingSalesCount' => $user->sales()->where('status', 'pending')->count(),
            'recentPayouts' => $user->commissionPayouts()->with('sale.seller')->latest()->limit(5)->get(),
            'referralLink' => $user->referralLink(),
        ]);
    }
}
