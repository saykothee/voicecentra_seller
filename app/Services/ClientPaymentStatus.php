<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out whether a client is current on their monthly payments.
 *
 * A client (grouped by client_id) is expected to pay once a month on the
 * day-of-month of their FIRST sale (the billing day). A month counts as paid
 * only when a sale for that client is marked paid (paid = true) and is not
 * refunded/rejected; the payment date is paid_at, falling back to sold_at.
 * Trials still count as payments but the client is flagged as on a trial.
 *
 * Status is one of:
 *   - 'late'       : the current billing date has passed unpaid → call them.
 *   - 'due_today'  : the billing date is today and unpaid.
 *   - 'to_be_paid' : current cycle covered; the next payment is in the future.
 *
 * Pure date math; no database access.
 */
class ClientPaymentStatus
{
    /**
     * Build a per-client report from a flat collection of sales (any client).
     * Each row merges client_id + seller with the computed status fields.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function report(Collection $sales, Carbon $today): Collection
    {
        return $sales
            ->filter(fn ($sale) => filled($sale->client_id) && $sale->status !== 'rejected')
            ->groupBy('client_id')
            ->map(function (Collection $clientSales) use ($today) {
                $anchor = $clientSales->sortBy('sold_at')->first();

                return array_merge(
                    ['client_id' => $anchor->client_id, 'seller' => $anchor->seller],
                    $this->forClient($clientSales, $today),
                );
            })
            ->values();
    }

    /**
     * Compute a single client's payment status from their sales.
     *
     * @param  Collection<int, object>  $sales  the client's sales (sold_at, paid_at, paid, trial, status)
     * @return array<string, mixed>
     */
    public function forClient(Collection $sales, Carbon $today): array
    {
        $sales = $sales->reject(fn ($sale) => $sale->status === 'rejected');
        $today = $today->copy()->startOfDay();

        $anchor = $sales->sortBy('sold_at')->first();
        $billingDay = (int) $anchor->sold_at->day;

        // Counting payments: marked paid and not refunded. Trials still count.
        $payments = $sales->filter(fn ($sale) => $sale->paid && $sale->status !== 'refunded');
        $lastPaymentDate = $payments
            ->map(fn ($sale) => ($sale->paid_at ?? $sale->sold_at)->copy()->startOfDay())
            ->sort()
            ->last();

        $currentDue = $this->currentDueDate($billingDay, $today);
        $covered = $lastPaymentDate !== null && $lastPaymentDate->gte($currentDue);

        if ($covered) {
            $status = 'to_be_paid';
            $nextDue = $this->billingDateIn($billingDay, $currentDue->copy()->addMonthNoOverflow());
            $daysLate = 0;
        } elseif ($today->equalTo($currentDue)) {
            $status = 'due_today';
            $nextDue = $currentDue;
            $daysLate = 0;
        } else {
            $status = 'late';
            $nextDue = $currentDue;
            $daysLate = (int) $currentDue->diffInDays($today);
        }

        // "On trial" reflects the client's most recent sale being a free trial.
        $onTrial = (bool) $sales->sortByDesc('sold_at')->first()->trial;

        return [
            'billing_day' => $billingDay,
            'first_sale_date' => $anchor->sold_at->copy()->startOfDay(),
            'last_payment_date' => $lastPaymentDate,
            'current_due_date' => $currentDue,
            'next_due_date' => $nextDue,
            'status' => $status,
            'days_late' => $daysLate,
            'on_trial' => $onTrial,
            'paid_count' => $payments->count(),
        ];
    }

    /** The most recent billing date on or before $today. */
    private function currentDueDate(int $billingDay, Carbon $today): Carbon
    {
        $thisMonth = $this->billingDateIn($billingDay, $today);

        return $today->gte($thisMonth)
            ? $thisMonth
            : $this->billingDateIn($billingDay, $today->copy()->subMonthNoOverflow());
    }

    /** The billing date within the given month, clamped to the month's length. */
    private function billingDateIn(int $billingDay, Carbon $month): Carbon
    {
        $first = Carbon::create($month->year, $month->month, 1)->startOfDay();

        return $first->day(min($billingDay, $first->daysInMonth));
    }
}
