<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionDistributor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function create()
    {
        $sellers = User::where('role', 'seller')->orderBy('name')->get(['id', 'name']);

        return view('admin.sales.create', compact('sellers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'seller_id' => ['required', Rule::exists('users', 'id')->where('role', 'seller')],
            'client_id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'sold_at' => ['required', 'date', 'before_or_equal:today'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'paid' => ['nullable', 'boolean'],
            'trial' => ['nullable', 'boolean'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // seller_id / status / approved_* are not mass-assignable (least privilege).
        $sale = new Sale([
            'client_id' => $data['client_id'],
            'amount_cents' => (int) round($data['amount'] * 100),
            'sold_at' => $data['sold_at'],
            'paid_at' => $data['paid_at'] ?? null,
            'paid' => $request->boolean('paid'),
            'trial' => $request->boolean('trial'),
            'notes' => $data['notes'] ?? null,
        ]);
        $sale->seller_id = (int) $data['seller_id'];
        $sale->status = 'pending'; // keep the in-memory model in sync with the DB default
        $sale->save();

        // Approving runs the same distribution path as the manual approve action,
        // so commissions/ledgers are written exactly as for a seller-reported sale.
        if ($data['status'] === 'approved') {
            $this->distributor->distribute($sale, $request->user());
        } elseif ($data['status'] === 'rejected') {
            $sale->status = 'rejected';
            $sale->save();
        }

        return redirect()->route('admin.sales.index')->with('status', __('messages.sale_created'));
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
