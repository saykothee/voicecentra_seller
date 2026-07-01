<?php

namespace App\Services;

/**
 * Builds the onboarding ramp schedule for an N-month projection from the tenure
 * thresholds in config('commissions.ramp'). The same rule applies at any length:
 * the seller earns nothing, then the flat stipend, then full commission; the chain
 * starts earning from its own threshold month.
 *
 * Pure; reads config only. No database access.
 */
class RampSchedule
{
    /**
     * @return array<int, array{seller: string, chain: string}> keyed by month (1..N),
     *   each 'seller' in {none, flat, full} and 'chain' in {none, full}.
     */
    public function forMonths(int $months): array
    {
        $flatFrom = (int) config('commissions.ramp.seller_flat_from');
        $fullFrom = (int) config('commissions.ramp.seller_full_from');
        $chainFrom = (int) config('commissions.ramp.chain_full_from');

        $schedule = [];
        for ($month = 1; $month <= $months; $month++) {
            $schedule[$month] = [
                'seller' => match (true) {
                    $month >= $fullFrom => 'full',
                    $month >= $flatFrom => 'flat',
                    default => 'none',
                },
                'chain' => $month >= $chainFrom ? 'full' : 'none',
            ];
        }

        return $schedule;
    }

    public function minMonths(): int
    {
        return (int) config('commissions.ramp.min_months');
    }

    public function maxMonths(): int
    {
        return (int) config('commissions.ramp.max_months');
    }

    public function defaultMonths(): int
    {
        return (int) config('commissions.ramp.default_months');
    }
}
