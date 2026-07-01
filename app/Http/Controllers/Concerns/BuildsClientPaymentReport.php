<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ClientPaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait BuildsClientPaymentReport
{
    /** Display order: the clients to call first. */
    private const STATUS_ORDER = ['late' => 0, 'due_today' => 1, 'to_be_paid' => 2];

    /**
     * Turn a collection of sales into the view payload: status counts (always over
     * the full set), plus the filtered + sorted (most-overdue-first) client rows.
     *
     * @return array{clients: Collection, counts: array<string, int>, status: ?string}
     */
    protected function buildClientPayments(Collection $sales, ?string $status, ClientPaymentStatus $service): array
    {
        $all = $service->report($sales, Carbon::today());

        $counts = [
            'late' => $all->where('status', 'late')->count(),
            'due_today' => $all->where('status', 'due_today')->count(),
            'to_be_paid' => $all->where('status', 'to_be_paid')->count(),
        ];

        $clients = $all
            ->when(array_key_exists((string) $status, self::STATUS_ORDER), fn ($c) => $c->where('status', $status))
            ->sort(function (array $a, array $b) {
                $byStatus = self::STATUS_ORDER[$a['status']] <=> self::STATUS_ORDER[$b['status']];

                return $byStatus !== 0 ? $byStatus : $b['days_late'] <=> $a['days_late'];
            })
            ->values();

        return ['clients' => $clients, 'counts' => $counts, 'status' => $status];
    }
}
