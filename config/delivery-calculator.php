<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Holidays cache
    |--------------------------------------------------------------------------
    |
    | The key under which the holidays collection is stored, and the time in
    | seconds the cached value is considered fresh. Holidays change rarely, so
    | a daily refresh is a sensible default.
    |
    */

    'cache' => [
        'key' => 'delivery_calculator_holidays',
        'ttl' => 86400,
    ],
];
