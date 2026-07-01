<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsClientPaymentReport;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\ClientPaymentStatus;
use Illuminate\Http\Request;

class AdminClientPaymentController extends Controller
{
    use BuildsClientPaymentReport;

    public function index(Request $request, ClientPaymentStatus $service)
    {
        // Every client across all sellers.
        $sales = Sale::query()
            ->whereNotNull('client_id')
            ->where('status', '!=', 'rejected')
            ->with('seller:id,name')
            ->get();

        return view('payments.clients', array_merge(
            $this->buildClientPayments($sales, $request->query('status'), $service),
            ['showSeller' => true, 'filterRoute' => 'admin.client-payments'],
        ));
    }
}
