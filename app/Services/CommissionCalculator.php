<?php

namespace App\Services;

/**
 * Pure commission math. No database access.
 *
 * All rates are exact fractions over config('commissions.denominator') (5120).
 * Amounts are integer cents; each payout is floored and the bonus pool absorbs
 * skipped levels, missing levels, and the rounding remainder, so:
 *   seller + paid uplines + pool == total_charge, exactly, for every input.
 */
class CommissionCalculator
{
    /**
     * @param int $amountCents sale amount in cents
     * @param array<int, bool> $uplineSlots level (1..9) => whether that upline is
     *        active. A missing level means the chain has no upline there.
     * @return array{
     *   seller_cents: int,
     *   levels: array<int, array{exists: bool, paid: bool, amount_cents: int, pool_reason: ?string}>,
     *   pool_rounding_cents: int,
     *   pool_total_cents: int,
     *   total_charge_cents: int
     * }
     */
    public function calculate(int $amountCents, array $uplineSlots): array
    {
        $den = (int) config('commissions.denominator');
        $autoLevels = (int) config('commissions.auto_levels');

        $sellerCents = intdiv($amountCents * (int) config('commissions.seller_numerator'), $den);
        $totalCharge = intdiv($amountCents * (int) config('commissions.total_numerator'), $den);

        $levels = [];
        $paidSum = $sellerCents;
        $poolFromLevels = 0;

        foreach (config('commissions.level_numerators') as $level => $numerator) {
            $amount = intdiv($amountCents * $numerator, $den);
            $exists = array_key_exists($level, $uplineSlots);
            $paid = $exists && ($level <= $autoLevels || $uplineSlots[$level]);

            $levels[$level] = [
                'exists' => $exists,
                'paid' => $paid,
                'amount_cents' => $amount,
                'pool_reason' => $paid ? null : ($exists ? 'inactive_upline' : 'no_upline'),
            ];

            if ($paid) {
                $paidSum += $amount;
            } else {
                $poolFromLevels += $amount;
            }
        }

        $rounding = $totalCharge - $paidSum - $poolFromLevels;

        if ($rounding < 0) {
            throw new \LogicException('Commission invariant violated: negative rounding remainder.');
        }

        return [
            'seller_cents' => $sellerCents,
            'levels' => $levels,
            'pool_rounding_cents' => $rounding,
            'pool_total_cents' => $poolFromLevels + $rounding,
            'total_charge_cents' => $totalCharge,
        ];
    }
}
