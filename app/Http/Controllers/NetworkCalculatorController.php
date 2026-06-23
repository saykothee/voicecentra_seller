<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\CommissionDistributor;
use App\Services\MinSalesLookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NetworkCalculatorController extends Controller
{
    public function show(Request $request)
    {
        $this->authorizeAccess($request->user());

        return view('calculator-2', [
            'sellers' => $this->sellers(),
            'result' => null,
            'members' => [],
            'chain' => [],
            'effectiveTotalCents' => 0,
            'sellerEffectiveCents' => 0,
            'input' => ['seller_id' => null, 'amount' => 1000],
        ]);
    }

    public function compute(
        Request $request,
        MinSalesLookup $lookup,
        CommissionCalculator $calculator,
        CommissionDistributor $distributor,
    ) {
        $this->authorizeAccess($request->user());

        $data = $request->validate([
            'seller_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'seller')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
        ]);

        $amountCents = (int) round($data['amount'] * 100);
        $seller = User::findOrFail($data['seller_id']);
        $chain = $seller->uplineChain(); // [level => User]

        // Per-member rows: seller (level 0) then each real upline.
        $sellerLookup = $lookup->forUser($seller);
        $members = [$this->memberRow(0, $seller, $sellerLookup, $amountCents)];
        foreach ($chain as $level => $upline) {
            $members[] = $this->memberRow($level, $upline, $lookup->forUser($upline), $amountCents);
        }
        $effectiveTotalCents = array_sum(array_column($members, 'effective_cents'));

        // Commission split on the SELLER's effective amount across the real chain.
        $sellerEffectiveCents = $amountCents * $sellerLookup['min_sales'];
        $uplineSlots = [];
        foreach ($chain as $level => $upline) {
            $uplineSlots[$level] = $distributor->isActive($upline, now());
        }
        $result = $calculator->calculate($sellerEffectiveCents, $uplineSlots);

        return view('calculator-2', [
            'sellers' => $this->sellers(),
            'result' => $result,
            'members' => $members,
            'chain' => $chain,
            'effectiveTotalCents' => $effectiveTotalCents,
            'sellerEffectiveCents' => $sellerEffectiveCents,
            'input' => ['seller_id' => (int) $data['seller_id'], 'amount' => $data['amount']],
        ]);
    }

    /** @param array{age:?int,label:?string,min_sales:int,matched:bool} $lookup */
    private function memberRow(int $level, User $user, array $lookup, int $amountCents): array
    {
        return [
            'level' => $level,
            'name' => $user->name,
            'age' => $lookup['age'],
            'label' => $lookup['label'],
            'min_sales' => $lookup['min_sales'],
            'matched' => $lookup['matched'],
            'effective_cents' => $amountCents * $lookup['min_sales'],
        ];
    }

    private function sellers()
    {
        return User::where('role', 'seller')->orderBy('name')->get(['id', 'name']);
    }

    private function authorizeAccess(?User $user): void
    {
        abort_unless($user && ($user->isAdmin() || ($user->isSeller() && $user->isApproved())), 403);
    }
}
