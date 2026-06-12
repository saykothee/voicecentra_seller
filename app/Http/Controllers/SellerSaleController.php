<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class SellerSaleController extends Controller
{
    public function index(Request $request)
    {
        $sales = $request->user()->sales()->latest('sold_at')->paginate(15);

        return view('seller.sales.index', compact('sales'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'sold_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = new Sale([
            'amount_cents' => (int) round($data['amount'] * 100),
            'sold_at' => $data['sold_at'],
            'notes' => $data['notes'] ?? null,
        ]);
        $sale->seller_id = $request->user()->id; // not mass-assignable
        $sale->save();

        return back()->with('status', __('messages.sale_submitted'));
    }
}
