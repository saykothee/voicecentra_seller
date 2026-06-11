<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isSeller() && $user->isApproved()) {
            return redirect()->route('seller.dashboard');
        }

        return redirect()->route('pending');
    }
}
