<?php

if (! function_exists('defaultTempUser')) {
    /**
     * In-memory persona for the default student portal account (no Auth).
     */
    function defaultTempUser(): object
    {
        return (object) [
            'id' => (int) config('student.temp_user_id', 1),
            'name' => (string) config('student.temp_user_name', 'Guest Student'),
            'email' => (string) config('student.temp_user_email', 'guest@student.local'),
        ];
    }
}

if (! function_exists('studentDisplayUser')) {
    /**
     * Merge session('user') over the default temp student persona.
     *
     * @param  array<string, mixed>|null  $fromSession
     */
    function studentDisplayUser(?array $fromSession): object
    {
        $base = (array) defaultTempUser();

        if (! is_array($fromSession)) {
            return (object) $base;
        }

        foreach (['id', 'name', 'email'] as $key) {
            if (! array_key_exists($key, $fromSession)) {
                continue;
            }
            $val = $fromSession[$key];
            if ($val === null || $val === '') {
                continue;
            }
            $base[$key] = $key === 'id' ? (int) $val : (string) $val;
        }

        return (object) $base;
    }
}
