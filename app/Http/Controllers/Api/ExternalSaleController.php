<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalSaleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'seller')],
            'client_id' => ['nullable', 'string', 'max:255'],
            'sale_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'paid' => ['required', 'boolean'],
            'free_trial' => ['required', 'boolean'],
        ]);

        // Recorded straight into the sales table as already-approved (it comes
        // from a trusted external system). The incoming `free_trial` maps to the
        // `trial` column and `sale_date` to `sold_at`.
        $sale = new Sale([
            'client_id' => $data['client_id'] ?? null,
            'amount_cents' => (int) round($data['amount'] * 100),
            'sold_at' => $data['sale_date'],
            'paid_at' => $data['paid_at'] ?? null,
            'paid' => $data['paid'],
            'trial' => $data['free_trial'],
        ]);
        $sale->seller_id = $data['seller_id']; // not mass-assignable
        $sale->status = 'approved';
        $sale->approved_at = now();
        $sale->save();

        return response()->json(['id' => $sale->id, 'status' => 'recorded'], 201);
    }
}
