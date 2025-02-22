<?php

namespace App\Http\Interfaces;

use Illuminate\Http\Request;

interface GameSessionsRepositoryInterface
{
    public function get(string $guid);

    public function create();

    public function update(string $guid, Request $request);
}