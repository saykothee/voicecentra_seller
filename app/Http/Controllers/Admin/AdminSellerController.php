<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SellerTree;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminSellerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $sellers = User::with('parent')->where('role', 'seller')
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

    public function editSponsor(User $user, SellerTree $tree)
    {
        abort_unless($user->isSeller(), 404);

        // Offer only sponsors that will pass updateSponsor's validation:
        // approved sellers outside the seller's own subtree whose depth leaves
        // room for the moved subtree within the max chain depth.
        $excludedIds = $tree->subtreeUsers($user->id)->pluck('id');
        $maxParentDepth = (int) config('commissions.max_depth') - $tree->subtreeHeight($user);

        $eligibleSponsors = User::where('role', 'seller')
            ->where('status', 'approved')
            ->whereNotIn('id', $excludedIds)
            ->where('depth', '<=', $maxParentDepth)
            ->orderBy('name')
            ->get();

        return view('admin.sellers.sponsor', [
            'seller' => $user,
            'eligibleSponsors' => $eligibleSponsors,
        ]);
    }

    public function updateSponsor(Request $request, User $user, SellerTree $tree)
    {
        abort_unless($user->isSeller(), 404);

        $data = $request->validate(['sponsor_email' => ['nullable', 'email']]);

        if (empty($data['sponsor_email'])) {
            $tree->changeSponsor($user, null);

            return redirect()->route('admin.sellers.index')->with('status', __('messages.sponsor_updated'));
        }

        $newParent = User::where('email', $data['sponsor_email'])
            ->where('role', 'seller')->where('status', 'approved')->first();

        if (! $newParent) {
            throw ValidationException::withMessages(['sponsor_email' => __('messages.sponsor_invalid')]);
        }

        if ($newParent->id === $user->id || $tree->isInSubtree($newParent, $user)) {
            throw ValidationException::withMessages(['sponsor_email' => __('messages.sponsor_cycle')]);
        }

        $newParent = $newParent->fresh();

        if ($newParent->depth + $tree->subtreeHeight($user) > (int) config('commissions.max_depth')) {
            throw ValidationException::withMessages(['sponsor_email' => __('messages.chain_full')]);
        }

        $tree->changeSponsor($user, $newParent);

        return redirect()->route('admin.sellers.index')->with('status', __('messages.sponsor_updated'));
    }
}
