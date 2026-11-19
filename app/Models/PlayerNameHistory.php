<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerNameHistory extends Model
{
    protected $table = 'player_name_history';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'name',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }
}
