<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Authenticatable;

/**
 * DEORIS User Model
 * 
 * This model represents users from the DEORIS database.
 * Users are managed centrally in DEORIS and accessed via a database connection.
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'deoris';

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'admission_status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Override the connection property to prevent it from being used as a where clause
     */
    public function getConnectionName()
    {
        return 'deoris';
    }

    /**
     * Get the user's full name (alias for name attribute).
     */
    public function getFullNameAttribute()
    {
        return $this->name;
    }
}
