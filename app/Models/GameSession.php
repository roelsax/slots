<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    protected $fillable = [
        'guid',
        'user_id',
        'active',
        'cashed_out',
        'cashed_out_amount',
        'current_game_credit'
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
