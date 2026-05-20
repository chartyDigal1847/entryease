<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default portal student (no login)
    |--------------------------------------------------------------------------
    |
    | Single shared User + Student used for all anonymous student traffic.
    | session('user') may override id/name/email when real auth is added later.
    |
    */
    'temp_user_id' => (int) env('STUDENT_TEMP_USER_ID', 1),

    'temp_user_name' => env('STUDENT_TEMP_USER_NAME', 'Guest Student'),

    'temp_user_email' => env('STUDENT_TEMP_USER_EMAIL', 'guest@student.local'),

];
