<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminSellerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $sellers = User::where('role', 'seller')
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true),
                fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.sellers.index', compact('sellers', 'status'));
    }

    public function approve(User $user)
    {
        abort_unless($user->isSeller(), 404);

        // role/status/audit fields are intentionally not in $fillable (least
        // privilege), so set them via explicit assignment rather than update([]).
        $user->status = 'approved';
        $user->approved_at = now();
        $user->approved_by = auth()->id();
        $user->save();

        return back()->with('status', __('messages.seller_approved'));
    }

    public function reject(User $user)
    {
        abort_unless($user->isSeller(), 404);

        $user->status = 'rejected';
        $user->approved_at = null;
        $user->approved_by = null;
        $user->save();

        return back()->with('status', __('messages.seller_rejected'));
    }
}
