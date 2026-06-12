<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusPoolEntry;

class AdminBonusPoolController extends Controller
{
    public function __invoke()
    {
        return view('admin.bonus-pool', [
            'balanceCents' => (int) BonusPoolEntry::sum('amount_cents'),
            'entries' => BonusPoolEntry::with('sale.seller')->latest()->paginate(20),
        ]);
    }
}
