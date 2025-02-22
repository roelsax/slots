<?php

namespace App\Http\Services;
use Illuminate\Http\Request;
use App\Http\Enums\SlotSymbols;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\GameSessionsRepository;

class SlotService {

    private $sessionsRepository;
    private $userRepository;

    public function __construct(GameSessionsRepository $sessionsRepository, UserRepository $userRepository)
    {
        $this->sessionsRepository = $sessionsRepository;
        $this->userRepository = $userRepository;
    }

    public function startSession()
    {
        return $this->sessionsRepository->create();
    }

    public function rollSlot(Request $request) 
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $cases = SlotSymbols::cases();
        $credits = $request->get('currentCredits');

        if ($credits < 0) {
            return response()->json(['error' => 'Incorrect amount of credits'], 422);
        }

        $guid = $request->get('guid');
        $credits--;

        $roll = $this->calculateRoll($credits, $cases);
        $roll['credits'] = $credits;

        if(array_key_exists('amount_won', $roll)) {
            $roll['credits'] = $credits + $roll['amount_won'];
        }
        
        $request->merge([
            'currentCredits' => $roll['credits'],
            'guid' => $guid
        ]);
        
        $this->updateSession($guid, $request);

        return response()->json($roll, 200);
    }

    public function updateSession(string $guid, Request $request) 
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $this->userRepository->update($request);

        return $this->sessionsRepository->update($guid, $request);
    }

    protected function calculateRoll(int $credits, array $cases, callable $randomGenerator = null) 
    {
        $randomGenerator = $randomGenerator ?: fn() => rand(1, 100);
        
        switch ($credits) {
            case ($credits < 40):
                return $this->roll($cases);
        
            case ($credits >= 40 && $credits <= 60):
                $rollData = $this->roll($cases);
                if (array_key_exists('amount_won', $rollData) && $randomGenerator() <= 30){
                    $rollData = $this->roll($cases);
                }
                return $rollData;
            case ($credits > 60):
                $rollData = $this->roll($cases);
                if (array_key_exists('amount_won', $rollData) && $randomGenerator() <= 60){
                    $rollData = $this->roll($cases);
                }
                return $rollData;
        
            default:
                return null;
        }
    }

    protected function roll(array $cases)
    {
        $rollData =  [
            "roll" => array_map(fn() => $cases[array_rand($cases)], range(1, 3))
        ];

        if ($this->checkIfWinningRoll($rollData["roll"])) {
            $firstRoll = $this->checkIfWinningRoll($rollData["roll"]);
            $rollData["amount_won"] = $firstRoll->getPoints();
        }
        return $rollData;
    }

    private function checkIfWinningRoll(array $roll) 
    {
        $firstValue = reset($roll);

        foreach ($roll as $value) {
            if ($value !== $firstValue) {
                return false;
            }
        } 

        return $firstValue;
    }
}