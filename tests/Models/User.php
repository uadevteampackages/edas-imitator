<?php

namespace Uadevteampackages\Imitator\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'principal_name',
        'username',
        'job_title',
    ];
}
