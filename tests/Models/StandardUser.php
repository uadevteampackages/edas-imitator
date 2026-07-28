<?php

namespace Uadevteampackages\Imitator\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class StandardUser extends Authenticatable
{
    protected $table = 'standard_users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
