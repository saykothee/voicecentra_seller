<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => User::where('role', 'seller')->count(),
            'pending' => User::where('role', 'seller')->where('status', 'pending')->count(),
            'approved' => User::where('role', 'seller')->where('status', 'approved')->count(),
            'rejected' => User::where('role', 'seller')->where('status', 'rejected')->count(),
        ];

        $salesStats = [
            'pending_sales' => \App\Models\Sale::where('status', 'pending')->count(),
            'volume_cents' => (int) \App\Models\Sale::where('status', 'approved')->sum('amount_cents'),
            'paid_cents' => (int) \App\Models\CommissionPayout::where('status', 'paid')->sum('amount_cents'),
            'pool_cents' => (int) \App\Models\BonusPoolEntry::sum('amount_cents'),
        ];

        return view('admin.dashboard', compact('stats', 'salesStats'));
    }
}
