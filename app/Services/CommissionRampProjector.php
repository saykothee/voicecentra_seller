<?php

namespace App\Services;

/**
 * Projects the seller's and the upline chain's monthly earnings across the
 * onboarding ramp ($schedule, of any length from App\Services\RampSchedule).
 *
 * Commissions are residual: a sale keeps earning every month, so each month's
 * earnings are computed on the ACTIVE (cumulative) base — every sale made through
 * that month. The ramp mode for the month decides what the seller and chain earn:
 *   - 'none' month → the member earns nothing.
 *   - 'flat' month → the seller earns a per-sale stipend (ramp.seller_flat_cents)
 *     times the number of active sales — paid again every flat month.
 *   - 'full' month → the member earns its full commission on the active base —
 *     paid again every full month.
 *
 * Pure integer-cents math; no database access and no side effects.
 */
class CommissionRampProjector
{
    /**
     * @param array<int, array{
     *   active_volume_cents: int,
     *   active_quantity: int,
     *   seller_full_cents: int,
     *   chain_full_cents: int
     * }> $monthly per-month ACTIVE (cumulative) base and its full commission split,
     *   keyed by month number (matching $schedule).
     * @param array<int, array{seller: string, chain: string}> $schedule the ramp
     *   modes per month, from App\Services\RampSchedule::forMonths().
     * @return list<array{
     *   month: int,
     *   active_volume_cents: int,
     *   active_quantity: int,
     *   seller_mode: string,
     *   chain_mode: string,
     *   seller_flat_cents: int,
     *   seller_commission_cents: int,
     *   seller_cents: int,
     *   chain_cents: int,
     *   total_cents: int
     * }>
     */
    public function project(array $monthly, array $schedule): array
    {
        $flatPerSaleCents = (int) config('commissions.ramp.seller_flat_cents');

        $rows = [];
        foreach ($schedule as $month => $modes) {
            $m = $monthly[$month];

            // The seller's cut has two parts: the per-sale flat stipend (flat months)
            // and the percentage commission (full months). A month is one or the other.
            $sellerFlatCents = $modes['seller'] === 'flat' ? $flatPerSaleCents * $m['active_quantity'] : 0;
            $sellerCommissionCents = $modes['seller'] === 'full' ? $m['seller_full_cents'] : 0;
            $sellerCents = $sellerFlatCents + $sellerCommissionCents;

            $chainCents = $modes['chain'] === 'full' ? $m['chain_full_cents'] : 0;

            $rows[] = [
                'month' => (int) $month,
                'active_volume_cents' => $m['active_volume_cents'],
                'active_quantity' => $m['active_quantity'],
                'seller_mode' => $modes['seller'],
                'chain_mode' => $modes['chain'],
                'seller_flat_cents' => $sellerFlatCents,
                'seller_commission_cents' => $sellerCommissionCents,
                'seller_cents' => $sellerCents,
                'chain_cents' => $chainCents,
                'total_cents' => $sellerCents + $chainCents,
            ];
        }

        return $rows;
    }
}
