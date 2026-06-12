<?php

namespace App\Http\Controllers;

use App\Services\SellerTree;
use Illuminate\Http\Request;

class SellerNetworkController extends Controller
{
    public function __invoke(Request $request, SellerTree $tree)
    {
        $me = $request->user();
        $node = $tree->subtree($me);
        $counts = $tree->recentSalesCounts($tree->subtreeUsers($me->id));

        return view('seller.network', [
            'node' => $node,
            'counts' => $counts,
            'sponsor' => $me->parent,
        ]);
    }
}
