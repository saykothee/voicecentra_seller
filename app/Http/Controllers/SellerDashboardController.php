<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SellerDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('seller.dashboard', ['user' => $request->user()]);
    }
}
