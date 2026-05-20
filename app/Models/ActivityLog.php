<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ActivityLog Model
 *
 * Maintains an audit trail of significant system actions.
 * Automatically keeps only the 50 most recent entries.
 */
class ActivityLog extends Model
{
    protected $fillable = ['message', 'type', 'at'];
    protected $casts = ['at' => 'datetime'];

    /**
     * Record a system activity
     *
     * @param string $message The activity description
     * @param string $type Color type: green, blue, red, amber, purple, gray (default: gray)
     */
    public static function record(string $message, string $type = 'gray'): void
    {
        static::create(['message' => $message, 'type' => $type, 'at' => now()]);

        // Keep only the 50 most recent entries
        $oldest = static::orderByDesc('at')->skip(50)->first();
        if ($oldest) {
            static::where('at', '<=', $oldest->at)->delete();
        }
    }
}
