<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Enums\SlotSymbols;
use App\Http\Services\SlotService;

class SlotController extends Controller
{
    public function __construct(private SlotService $slotService) 
    {
        $this->slotService = $slotService;
    }

    public function startSession() 
    {
        return $this->slotService->startSession();
    }

    public function startGame(Request $request) 
    {
        return $this->slotService->rollSlot($request);
    }

    public function updateSession(string $guid, Request $request) 
    {
        return $this->slotService->updateSession($guid, $request);
    }
}
