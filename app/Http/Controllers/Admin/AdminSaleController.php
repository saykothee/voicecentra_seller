<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\CommissionDistributor;
use Illuminate\Http\Request;

class AdminSaleController extends Controller
{
    public function __construct(private CommissionDistributor $distributor)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status');

        $sales = Sale::with('seller')
            ->when(in_array($status, ['pending', 'approved', 'rejected', 'refunded'], true),
                fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.sales.index', compact('sales', 'status'));
    }

    public function approve(Request $request, Sale $sale)
    {
        abort_unless($sale->status === 'pending', 404);

        $this->distributor->distribute($sale, $request->user());

        return back()->with('status', __('messages.sale_approved'));
    }

    public function reject(Sale $sale)
    {
        abort_unless($sale->status === 'pending', 404);

        $sale->status = 'rejected';
        $sale->save();

        return back()->with('status', __('messages.sale_rejected'));
    }

    public function refund(Sale $sale)
    {
        abort_unless($sale->status === 'approved', 404);

        $this->distributor->refund($sale);

        return back()->with('status', __('messages.sale_refunded'));
    }
}
