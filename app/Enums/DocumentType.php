<?php

namespace App\Enums;

/** Urutan deklarasi = urutan tampil di layar cek berkas. */
enum DocumentType: string
{
    // Wajib
    case AktaKelahiran = 'akta_kelahiran';
    case Kk = 'kk';
    case Skl = 'skl';
    case Foto = 'foto';
    // Orang tua / wali
    case KtpAyah = 'ktp_ayah';
    case KtpIbu = 'ktp_ibu';
    case KtpWali = 'ktp_wali';
    // Kondisional
    case Sktm = 'sktm';
    case Kks = 'kks';
    case Pkh = 'pkh';
    case Sertifikat = 'sertifikat';

    public const GROUP_LABELS = [
        'wajib'       => 'Berkas wajib',
        'orangtua'    => 'KTP orang tua / wali',
        'kondisional' => 'Berkas kondisional',
    ];

    public function label(): string
    {
        return match ($this) {
            self::AktaKelahiran => 'Akta kelahiran',
            self::Kk            => 'Kartu Keluarga (KK)',
            self::Skl           => 'FC SKL',
            self::Foto          => 'Foto diri',
            self::KtpAyah       => 'KTP ayah',
            self::KtpIbu        => 'KTP ibu',
            self::KtpWali       => 'KTP wali',
            self::Sktm          => 'SKTM',
            self::Kks           => 'Kartu KKS',
            self::Pkh           => 'Kartu PKH',
            self::Sertifikat    => 'Sertifikat kejuaraan',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AktaKelahiran, self::Kk, self::Skl, self::Foto => 'wajib',
            self::KtpAyah, self::KtpIbu, self::KtpWali           => 'orangtua',
            default                                               => 'kondisional',
        };
    }

    /** Berkas wajib hanya boleh Belum atau Diterima. Lainnya boleh juga "Tidak ada". */
    public function allowedStatuses(): array
    {
        return $this->group() === 'wajib'
            ? [DocumentStatus::Belum, DocumentStatus::Diterima]
            : DocumentStatus::cases();
    }

    /** @return array<string, list<self>> kelompok => jenis berkas */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $type) {
            $groups[$type->group()][] = $type;
        }

        return $groups;
    }
}
