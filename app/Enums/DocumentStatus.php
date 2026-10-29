<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Belum = 'belum';
    case Diterima = 'diterima';
    case TidakAda = 'tidak_ada';

    public function label(): string
    {
        return match ($this) {
            self::Belum    => 'Belum diterima',
            self::Diterima => 'Diterima',
            self::TidakAda => 'Tidak ada',
        };
    }
}
