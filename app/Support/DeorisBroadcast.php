<?php

namespace App\Support;

class DeorisBroadcast
{
    public static function isEnabled(): bool
    {
        if (! (bool) config('deoris.broadcast.enabled', false)) {
            return false;
        }

        $connection = (string) config('broadcasting.default', 'log');

        if (! in_array($connection, ['reverb', 'pusher'], true)) {
            return false;
        }

        if (! class_exists(\Pusher\Pusher::class)) {
            return false;
        }

        $key = config('broadcasting.connections.reverb.key')
            ?: config('broadcasting.connections.pusher.key');

        return filled($key);
    }
}
