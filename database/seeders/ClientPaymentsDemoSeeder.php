<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for the Client Payments page: 10 clients (across two sellers) with
 * monthly payments over the last two months, engineered to hit all three
 * statuses — 4 late, 3 due today, 3 to be paid (one on a free trial).
 *
 * NOT registered in DatabaseSeeder, so `php artisan db:seed` skips it. Run it
 * explicitly when you want the demo data:
 *
 *   php artisan db:seed --class=ClientPaymentsDemoSeeder
 *
 * Dates are relative to today, so the statuses stay correct on any day (run it
 * past roughly the 5th of the month for the clearest late/due-today spread).
 * Re-running refreshes the same client ids instead of duplicating them.
 */
class ClientPaymentsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $dT = $today->day;

        $sellers = User::where('role', 'seller')->where('status', 'approved')->orderBy('id')->take(2)->get();
        if ($sellers->isEmpty()) {
            $sellers = User::factory()->approvedSeller()->count(2)->create();
        }
        $a = $sellers->first()->id;
        $b = ($sellers->count() > 1 ? $sellers[1]->id : $a);

        $plan = [
            // late: billing day before today, current cycle left unpaid
            ['id' => 'CUST-1001', 'seller' => $a, 'day' => max(1, $dT - 3),  'status' => 'late'],
            ['id' => 'CUST-1002', 'seller' => $a, 'day' => max(1, $dT - 7),  'status' => 'late'],
            ['id' => 'CUST-1003', 'seller' => $a, 'day' => max(1, $dT - 11), 'status' => 'late'],
            ['id' => 'CUST-1004', 'seller' => $b, 'day' => max(1, $dT - 15), 'status' => 'late'],
            // due today: billing day == today, current cycle unpaid
            ['id' => 'CUST-1005', 'seller' => $a, 'day' => $dT, 'status' => 'due_today'],
            ['id' => 'CUST-1006', 'seller' => $b, 'day' => $dT, 'status' => 'due_today'],
            ['id' => 'CUST-1007', 'seller' => $b, 'day' => $dT, 'status' => 'due_today'],
            // to be paid: current cycle already paid (one on a free trial)
            ['id' => 'CUST-1008', 'seller' => $a, 'day' => max(1, $dT - 2), 'status' => 'to_be_paid'],
            ['id' => 'CUST-1009', 'seller' => $b, 'day' => max(1, $dT - 5), 'status' => 'to_be_paid'],
            ['id' => 'CUST-1010', 'seller' => $b, 'day' => max(1, $dT - 9), 'status' => 'to_be_paid', 'trial' => true],
        ];

        // Re-runnable: clear any prior demo rows for these clients first.
        Sale::whereIn('client_id', array_column($plan, 'id'))->delete();

        foreach ($plan as $c) {
            $trial = $c['trial'] ?? false;
            // Three monthly payments: two months ago, last month, this month.
            foreach ([2, 1, 0] as $monthsAgo) {
                $date = $this->billingDate($today, $monthsAgo, $c['day']);
                // The current month's row is left unpaid for late / due-today clients.
                $paid = ! ($monthsAgo === 0 && $c['status'] !== 'to_be_paid');

                $sale = new Sale([
                    'client_id' => $c['id'],
                    'amount_cents' => random_int(5_000, 50_000), // $50.00 - $500.00
                    'sold_at' => $date,
                    'paid_at' => $paid ? $date : null,
                    'paid' => $paid,
                    'trial' => $trial,
                ]);
                $sale->seller_id = $c['seller']; // not mass-assignable
                $sale->status = 'approved';
                $sale->approved_at = Carbon::now();
                $sale->save();
            }
        }
    }

    /** The billing date $monthsAgo back, on $day (clamped to the month's length). */
    private function billingDate(Carbon $today, int $monthsAgo, int $day): Carbon
    {
        $month = $today->copy()->startOfMonth()->subMonthsNoOverflow($monthsAgo);

        return $month->copy()->day(min($day, $month->daysInMonth));
    }
}
