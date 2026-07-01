<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsClientPaymentReport;
use App\Services\ClientPaymentStatus;
use Illuminate\Http\Request;

class SellerClientPaymentController extends Controller
{
    use BuildsClientPaymentReport;

    public function index(Request $request, ClientPaymentStatus $service)
    {
        // Only this seller's own clients.
        $sales = $request->user()->sales()
            ->whereNotNull('client_id')
            ->where('status', '!=', 'rejected')
            ->with('seller:id,name')
            ->get();

        return view('payments.clients', array_merge(
            $this->buildClientPayments($sales, $request->query('status'), $service),
            ['showSeller' => false, 'filterRoute' => 'seller.client-payments'],
        ));
    }
}
