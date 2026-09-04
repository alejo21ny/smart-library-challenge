<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default loan period
    |--------------------------------------------------------------------------
    |
    | Number of days a book stays borrowed before it's due, unless a specific
    | loan overrides it. A config value, not a magic number scattered in code.
    |
    */
    'loan_period_days' => (int) env('LIBRARY_LOAN_PERIOD_DAYS', 14),
];
