<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerProfile extends Model
{
    protected $table = 'player_profiles';

    protected $primaryKey = 'account_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = true;

    const CREATED_AT = null;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'account_id',
        'nickname',
        'avatar',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'updated_at' => 'datetime',
    ];
}
