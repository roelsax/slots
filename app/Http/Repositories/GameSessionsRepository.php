<?php 

namespace App\Http\Repositories;

use App\Models\GameSession;
use Illuminate\Http\Request;

use App\Http\Interfaces\GameSessionsRepositoryInterface;

class GameSessionsRepository implements GameSessionsRepositoryInterface
{
    public function get(string $guid)
    {
        return GameSession::where('guid', $guid)->first();
    }

    public function create()
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $session = GameSession::where('user_id', auth()->user()->id)->where('active', true)->first();
        
        if(empty($session))
        {
            $session = GameSession::create([
                'guid' => $this->createGUID(),
                'user_id' => auth()->user()->id,
                'active' => true,
                'cashed_out' => false,
                'current_game_credit' => 10
            ]);
        }

        return response()->json($session, 201);
    }

    public function update(string $guid, Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $session = $this->get($guid);

        if ($session) {
            $session->update([
                'active' => $request->get('active'),
                'cashed_out' => $request->get('cashed_out'),
                'cashed_out_amount' => $request->get('cashed_out_amount'),
                'current_game_credit' => $request->get('currentCredits') ?? 0
            ]);
            
            $session->save();
            return response()->json($session, 200);
        } else {
            return response()->json(['error' => 'Session not found'], 404);
        }

        
    }

    function createGUID(){
        if (function_exists('com_create_guid'))
            return trim(com_create_guid(), '{}'); 
        else {
            mt_srand((double)microtime() * 10000); 
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45); // "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
            return $uuid;
        }
    }
}