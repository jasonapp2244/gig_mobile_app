<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Auto-Refresh Interval
    |--------------------------------------------------------------------------
    |
    | How often (in seconds) the admin income tracker pages fetch new activity
    | in the background.
    |
    */

    'refresh_seconds' => (int) env('INCOME_TRACKER_REFRESH_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Screen View Throttle
    |--------------------------------------------------------------------------
    |
    | A user opening income tracker screens is logged at most once per this
    | many minutes, so repeated opens don't flood the activity log.
    |
    */

    'view_throttle_minutes' => (int) env('INCOME_TRACKER_VIEW_THROTTLE_MINUTES', 5),

];
