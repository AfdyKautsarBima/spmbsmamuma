<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Terdaftar = 'terdaftar';
    case Menyicil = 'menyicil';
    case Lunas = 'lunas';
    case Mundur = 'mundur';

    public function label(): string
    {
        return match ($this) {
            self::Terdaftar => 'Terdaftar',
            self::Menyicil  => 'Daftar ulang, menyicil',
            self::Lunas     => 'Daftar ulang, lunas',
            self::Mundur    => 'Mengundurkan diri',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Terdaftar => 'bg-slate-100 text-slate-700',
            self::Menyicil  => 'bg-amber-100 text-amber-800',
            self::Lunas     => 'bg-green-100 text-green-800',
            self::Mundur    => 'bg-red-100 text-red-700',
        };
    }
}
