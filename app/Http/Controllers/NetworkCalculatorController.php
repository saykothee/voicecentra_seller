<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\CommissionDistributor;
use App\Services\CommissionRampProjector;
use App\Services\MinSalesLookup;
use App\Services\RampSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NetworkCalculatorController extends Controller
{
    /** Per-row defaults for the monthly grid (also used to fill months added on resize). */
    private const DEFAULT_AMOUNT = 199;
    private const DEFAULT_QUANTITY = 1;

    public function show(Request $request, RampSchedule $rampSchedule)
    {
        $this->authorizeAccess($request->user());

        $months = $rampSchedule->defaultMonths();

        return view('calculator-2', [
            'sellers' => $this->sellers(),
            'computed' => false,
            'members' => [],
            'chain' => [],
            'effectiveTotalCents' => 0,
            'projection' => [],
            'totalVolumeCents' => 0,
            'months' => $months,
            'monthsMin' => $rampSchedule->minMonths(),
            'monthsMax' => $rampSchedule->maxMonths(),
            'input' => [
                'seller_id' => null,
                'amounts' => array_fill_keys(range(1, $months), self::DEFAULT_AMOUNT),
                'quantities' => array_fill_keys(range(1, $months), self::DEFAULT_QUANTITY),
            ],
        ]);
    }

    public function compute(
        Request $request,
        MinSalesLookup $lookup,
        CommissionCalculator $calculator,
        CommissionDistributor $distributor,
        CommissionRampProjector $projector,
        RampSchedule $rampSchedule,
    ) {
        $this->authorizeAccess($request->user());

        // Validate the projection length first so the per-month rules size safely.
        $request->validate([
            'months' => ['required', 'integer', 'min:'.$rampSchedule->minMonths(), 'max:'.$rampSchedule->maxMonths()],
        ]);
        $months = (int) $request->input('months');
        $schedule = $rampSchedule->forMonths($months);

        // Per-month inputs: amount[m] (per sale) and quantity[m] (sales that month).
        // Provided values are validated; months left out (e.g. rows added when the
        // projection length grows) fall back to the row defaults instead of erroring.
        // quantity 0 means no sales that month → $0 for everyone.
        $data = $request->validate([
            'seller_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'seller')],
            'amount' => ['array'],
            'amount.*' => ['numeric', 'min:0.01', 'max:100000000'],
            'quantity' => ['array'],
            'quantity.*' => ['integer', 'min:0', 'max:10000'],
        ]);
        $amounts = $data['amount'] ?? [];
        $quantities = $data['quantity'] ?? [];

        $seller = User::findOrFail($data['seller_id']);
        $chain = $seller->uplineChain(); // [level => User]
        $sellerLookup = $lookup->forUser($seller);

        // Activity is evaluated once (as of now); it applies to every month.
        $uplineSlots = [];
        foreach ($chain as $level => $upline) {
            $uplineSlots[$level] = $distributor->isActive($upline, now());
        }

        // Residual model: a sale keeps earning every month, so the active base
        // accumulates. Each month's commission is computed on the cumulative volume
        // (every sale made through that month); the ramp mode then decides the split.
        // The largest value handed to the integer commission math is the final
        // effective base; keep it (× the largest numerator) within PHP_INT_MAX so a
        // long projection of very large sales fails cleanly instead of overflowing
        // into a 500 inside intdiv().
        $effectiveCeiling = intdiv(PHP_INT_MAX, (int) config('commissions.total_numerator'));

        $monthly = [];
        $resolvedAmounts = [];
        $resolvedQuantities = [];
        $activeVolumeCents = 0;
        $activeQuantity = 0;
        foreach (array_keys($schedule) as $month) {
            // Resolve defensively: a missing or non-numeric value falls back to the
            // row default rather than crashing the integer math below.
            $amountRaw = $amounts[$month] ?? null;
            $qtyRaw = $quantities[$month] ?? null;
            $amountValue = is_numeric($amountRaw) ? $amountRaw : self::DEFAULT_AMOUNT;
            $qtyValue = is_numeric($qtyRaw) ? (int) $qtyRaw : self::DEFAULT_QUANTITY;
            $resolvedAmounts[$month] = $amountValue;
            $resolvedQuantities[$month] = $qtyValue;

            $activeVolumeCents += (int) round($amountValue * 100) * $qtyValue;
            $activeQuantity += $qtyValue;

            if ($activeVolumeCents * $sellerLookup['min_sales'] > $effectiveCeiling) {
                throw ValidationException::withMessages(['months' => __('messages.projection_too_large')]);
            }

            // Commission split on the SELLER's effective (grossed-up) active volume.
            $result = $calculator->calculate($activeVolumeCents * $sellerLookup['min_sales'], $uplineSlots);

            // The chain earns the upline cuts that are actually paid; skipped or
            // inactive levels fall to the pool, not the chain.
            $chainPaidCents = array_sum(array_map(
                fn (array $line) => $line['paid'] ? $line['amount_cents'] : 0,
                $result['levels'],
            ));

            $monthly[$month] = [
                'active_volume_cents' => $activeVolumeCents,
                'active_quantity' => $activeQuantity,
                'seller_full_cents' => $result['seller_cents'],
                'chain_full_cents' => $chainPaidCents,
            ];
        }

        $projection = $projector->project($monthly, $schedule);
        $totalVolumeCents = $activeVolumeCents; // final active base = all sales made

        // Per-member breakdown over the full active base (seller then real uplines).
        $members = [$this->memberRow(0, $seller, $sellerLookup, $totalVolumeCents)];
        foreach ($chain as $level => $upline) {
            $members[] = $this->memberRow($level, $upline, $lookup->forUser($upline), $totalVolumeCents);
        }
        $effectiveTotalCents = array_sum(array_column($members, 'effective_cents'));

        return view('calculator-2', [
            'sellers' => $this->sellers(),
            'computed' => true,
            'members' => $members,
            'chain' => $chain,
            'effectiveTotalCents' => $effectiveTotalCents,
            'projection' => $projection,
            'totalVolumeCents' => $totalVolumeCents,
            'months' => $months,
            'monthsMin' => $rampSchedule->minMonths(),
            'monthsMax' => $rampSchedule->maxMonths(),
            'input' => [
                'seller_id' => (int) $data['seller_id'],
                'amounts' => $resolvedAmounts,
                'quantities' => $resolvedQuantities,
            ],
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
