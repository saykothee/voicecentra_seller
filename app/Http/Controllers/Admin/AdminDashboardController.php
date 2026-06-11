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
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
