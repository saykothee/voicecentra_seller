<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalSaleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'seller')],
            'sale_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'paid' => ['required', 'boolean'],
            'free_trial' => ['required', 'boolean'],
        ]);

        $sale = ExternalSale::create([
            'seller_id' => $data['seller_id'],
            'sale_date' => $data['sale_date'],
            'paid_at' => $data['paid_at'] ?? null,
            'amount_cents' => (int) round($data['amount'] * 100),
            'paid' => $data['paid'],
            'free_trial' => $data['free_trial'],
        ]);

        return response()->json(['id' => $sale->id, 'status' => 'recorded'], 201);
    }
}
