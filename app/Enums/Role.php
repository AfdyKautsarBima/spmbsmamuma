<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Pendaftaran = 'pendaftaran';
    case DaftarUlang = 'daftar_ulang';
    case Seragam = 'seragam';

    public function label(): string
    {
        return match ($this) {
            self::Admin       => 'Admin',
            self::Pendaftaran => 'Panitia Pendaftaran',
            self::DaftarUlang => 'Panitia Daftar Ulang',
            self::Seragam     => 'Panitia Seragam',
        };
    }
}
