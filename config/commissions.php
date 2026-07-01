<?php

return [
    // All rates are exact fractions over this denominator.
    'denominator' => 5120,
    'seller_numerator' => 512,           // 10%
    'level_numerators' => [
        1 => 256,  // 5%
        2 => 128,  // 2.5%
        3 => 64,   // 1.25%
        4 => 32,   // 0.625%
        5 => 16,   // 0.3125%
        6 => 8,    // 0.15625%
        7 => 4,    // 0.078125%
        8 => 2,    // 0.0390625%
        9 => 1,    // 0.01953125%
    ],
    'total_numerator' => 1023,           // 19.98046875% total charge per sale

    'max_depth' => 10,                   // max people in a chain (seller + 9 uplines)
    'auto_levels' => 3,                  // upline levels 1..3 always pay
    'min_sales_quarter' => (int) env('COMMISSION_MIN_SALES_QUARTER', 2),
    'activity_window_days' => 90,

    // Onboarding ramp for Calculator 2.0's projection. The schedule is generated for
    // any month count (App\Services\RampSchedule) from these tenure thresholds:
    //   seller: month < seller_flat_from → 'none' (earns nothing);
    //           seller_flat_from..seller_full_from-1 → 'flat' (per-sale stipend);
    //           >= seller_full_from → 'full' (percentage commission).
    //   chain:  month < chain_full_from → 'none'; >= chain_full_from → 'full'.
    // The projection length is operator-selectable between min_months and max_months.
    'ramp' => [
        'seller_flat_cents' => 5000, // $50 flat seller stipend per sale during the flat months
        'seller_flat_from' => 2,     // seller starts earning the flat stipend this month
        'seller_full_from' => 5,     // seller starts earning full commission this month
        'chain_full_from' => 2,      // chain starts earning commission this month
        'min_months' => 6,           // shortest selectable projection (6 months)
        'max_months' => 60,          // longest selectable projection (5 years)
        'default_months' => 6,
    ],
];
