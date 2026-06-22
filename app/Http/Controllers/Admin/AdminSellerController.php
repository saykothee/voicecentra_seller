<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SellerTree;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    // Intentionally edits ANY user, not just sellers (admin-only tool). Role is
    // editable both ways, so a promoted seller must stay editable as an admin to
    // be demoted later; restricting to isSeller() would strand them. The
    // self-lockout guard in update() prevents an admin demoting their own account.
    public function edit(User $user)
    {
        return view('admin.sellers.edit', ['seller' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'status' => ['required', 'in:pending,approved,rejected'],
            'role' => ['required', 'in:seller,admin'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->date_of_birth = $data['date_of_birth'] ?? null;

        // Self-lockout guard: never change your own role/status from this admin form.
        if ($user->id !== auth()->id()) {
            $user->role = $data['role'];

            if ($data['status'] === 'approved' && $user->status !== 'approved') {
                $user->status = 'approved';
                $user->approved_at = now();
                $user->approved_by = auth()->id();
            } elseif ($data['status'] !== 'approved') {
                $user->status = $data['status'];
                $user->approved_at = null;
                $user->approved_by = null;
            }
        }

        $user->save();

        return redirect()->route('admin.sellers.index')->with('status', __('messages.profile_updated'));
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
