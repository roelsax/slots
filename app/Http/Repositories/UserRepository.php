<?php 

namespace App\Http\Repositories;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Interfaces\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface 
{
    public function get()
    {
        return Auth::user();
    }

    public function update(Request $request) 
    {
        $user = $this->get();
        
        Auth::user()->update([
            'credit_total' => $user->credit_total + $request->get('cashed_out_amount'),
            'last_game_session' => $request->get('guid')
        ]);
        
        Auth::user()->save();
    }
}