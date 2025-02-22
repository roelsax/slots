<?php 

namespace App\Http\Enums;

enum SlotSymbols : string
{
    case Cherry = 'C';
    case Lemon = 'L';
    case Orange = 'O';
    case Watermelon = 'W';

    public function getPoints(): int
    {
        return match ($this) {
            self::Cherry => 10,
            self::Lemon => 20,
            self::Orange => 30,
            self::Watermelon => 40,
        };
    }
}

