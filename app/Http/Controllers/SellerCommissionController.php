<?php

namespace App\Http\Controllers;

use App\Models\CommissionPayout;
use Illuminate\Http\Request;

class SellerCommissionController extends Controller
{
    public function __invoke(Request $request)
    {
        $payouts = CommissionPayout::with('sale.seller')
            ->where('recipient_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        $totalCents = (int) CommissionPayout::where('recipient_id', $request->user()->id)
            ->where('status', 'paid')->sum('amount_cents');

        return view('seller.commissions', compact('payouts', 'totalCents'));
    }
}
