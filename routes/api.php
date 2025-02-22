<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SlotController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::middleware('web')->post('/start-session', [SlotController::class, 'startSession']);
Route::middleware('web')->post('/start-game', [SlotController::class, 'startGame']);
Route::middleware('web')->post('/{guid}/update-session', [SlotController::class, 'updateSession']);
