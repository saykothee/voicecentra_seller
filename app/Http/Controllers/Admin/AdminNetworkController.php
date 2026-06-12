<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SellerTree;

class AdminNetworkController extends Controller
{
    public function __invoke(SellerTree $tree)
    {
        $forest = $tree->forest();
        $counts = $tree->recentSalesCounts(User::where('role', 'seller')->get());

        return view('admin.network', compact('forest', 'counts'));
    }
}
