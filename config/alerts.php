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

    /*
    |--------------------------------------------------------------------------
    | Geofence Deviation
    |--------------------------------------------------------------------------
    |
    | How far the recorded GPS position at work start or work end may sit from
    | the order location before it counts as a deviation and raises an alert.
    | GPS accuracy in an urban courtyard is typically 10–30 m, so the default
    | is deliberately generous. Set to 0 to disable the check entirely.
    |
    */

    'geofence_radius_meters' => (float) env('ALERT_GEOFENCE_RADIUS_METERS', 150),
];