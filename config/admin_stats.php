<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Users Hidden From Admin Statistics
    |--------------------------------------------------------------------------
    |
    | Internal team / test accounts are kept in the database but left out of
    | the admin dashboard numbers, charts and activity feeds, and the income
    | tracker pages. A user is hidden when ANY rule below matches.
    |
    */

    // Exact email addresses (case-insensitive).
    'excluded_emails' => [
        'tauseefchoohan0401@gmail.com',
        'tauseefchoohan0401+admin@gmail.com',
        'fazal2425abbas@gmail.com',
        'sameedbusiness777@gmail.com',
        'noumanzindani@gmail.com',
        'noumanzindanii@gmail.com',
        'noumanzindanicraftech@gmail.com',
        'noumanzindanicraftechdigital@gmail.com',
        'tnoumanzindanicraftechdigital@gmail.com',
    ],

    // Users whose profile timezone is one of these.
    'excluded_timezones' => [
        'Asia/Karachi',
    ],

    // Regex matched against the phone number with every non-digit removed.
    // Pakistani mobiles: 03xx xxxxxxx, +92 3xx xxxxxxx, 0092 3xx xxxxxxx.
    'excluded_phone_pattern' => '/^(03\d{9,10}|923\d{9}|00923\d{9})$/',

    // Minutes the resolved list of hidden user ids is cached.
    'cache_minutes' => 10,

];
