<?php

namespace App\Services;

use App\Models\BonusPoolEntry;
use App\Models\CommissionPayout;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persists commission ledgers when a sale is approved. The payout rows are the
 * immutable snapshot of the chain (recipient, level, rate, active flag) at
 * distribution time; later sponsor changes never touch them.
 */
class CommissionDistributor
{
    public function __construct(private CommissionCalculator $calculator)
    {
    }

    public function distribute(Sale $sale, User $approver): void
    {
        if ($sale->status !== 'pending') {
            throw new \LogicException('Only pending sales can be approved.');
        }

        DB::transaction(function () use ($sale, $approver) {
            $chain = $sale->seller->uplineChain();

            $uplineSlots = [];
            foreach ($chain as $level => $upline) {
                $uplineSlots[$level] = $this->isActive($upline, $sale->sold_at);
            }

            $result = $this->calculator->calculate($sale->amount_cents, $uplineSlots);

            CommissionPayout::create([
                'sale_id' => $sale->id,
                'recipient_id' => $sale->seller_id,
                'level' => 0,
                'rate_numerator' => (int) config('commissions.seller_numerator'),
                'amount_cents' => $result['seller_cents'],
                'recipient_was_active' => true,
                'status' => 'paid',
            ]);

            $numerators = config('commissions.level_numerators');

            foreach ($result['levels'] as $level => $line) {
                if ($line['paid']) {
                    CommissionPayout::create([
                        'sale_id' => $sale->id,
                        'recipient_id' => $chain[$level]->id,
                        'level' => $level,
                        'rate_numerator' => $numerators[$level],
                        'amount_cents' => $line['amount_cents'],
                        'recipient_was_active' => $uplineSlots[$level],
                        'status' => 'paid',
                    ]);
                } elseif ($line['amount_cents'] > 0) {
                    BonusPoolEntry::create([
                        'sale_id' => $sale->id,
                        'level' => $level,
                        'amount_cents' => $line['amount_cents'],
                        'reason' => $line['pool_reason'],
                    ]);
                }
            }

            if ($result['pool_rounding_cents'] > 0) {
                BonusPoolEntry::create([
                    'sale_id' => $sale->id,
                    'level' => null,
                    'amount_cents' => $result['pool_rounding_cents'],
                    'reason' => 'rounding',
                ]);
            }

            $sale->status = 'approved';
            $sale->approved_by = $approver->id;
            $sale->approved_at = now();
            $sale->save();
        });
    }

    /**
     * Active = at least MIN_SALES_QUARTER approved sales with sold_at inside the
     * window ending at $at (the evaluated sale's sold_at — never recomputed later).
     */
    public function isActive(User $seller, Carbon $at): bool
    {
        $windowStart = $at->copy()->subDays((int) config('commissions.activity_window_days'));

        return Sale::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->whereBetween('sold_at', [$windowStart, $at])
            ->count() >= (int) config('commissions.min_sales_quarter');
    }

    public function refund(Sale $sale): void
    {
        if ($sale->status !== 'approved') {
            throw new \LogicException('Only approved sales can be refunded.');
        }

        DB::transaction(function () use ($sale) {
            $sale->payouts()->update(['status' => 'reversed']);

            $poolSum = (int) $sale->poolEntries()->sum('amount_cents');
            if ($poolSum > 0) {
                BonusPoolEntry::create([
                    'sale_id' => $sale->id,
                    'level' => null,
                    'amount_cents' => -$poolSum,
                    'reason' => 'refund_reversal',
                ]);
            }

            $sale->status = 'refunded';
            $sale->save();
        });
    }
}
