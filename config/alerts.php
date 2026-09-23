<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Smart Admin Alert Evaluation
    |--------------------------------------------------------------------------
    |
    | Threshold for the monthly-hours governance alert. An employee is flagged
    | when their actual booked hours fall below this share of the contracted
    | hours for the month.
    |
    */

    'min_monthly_hours_percent' => (float) env('ALERT_MIN_MONTHLY_HOURS_PERCENT', 0.8),
];