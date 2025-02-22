<?php

namespace App\Http\Interfaces;

use Illuminate\Http\Request;

interface UserRepositoryInterface
{
    public function get();

    public function update(Request $request);
}