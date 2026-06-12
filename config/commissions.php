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
];
