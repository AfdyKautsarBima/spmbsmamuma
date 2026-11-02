<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\ApplicantUniformSize;
use App\Models\UniformEntitlement;
use App\Models\UniformHandover;
use App\Models\UniformSize;
use App\Models\UniformType;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Aturan seragam: hak (dihitung dari pembayaran), ukuran, dan penyerahan.
 * Pelanggaran aturan dilempar sebagai DomainException dengan pesan siap tampil.
 */
class UniformService
{
    /** Ambang rupiah = persen dari tagihan bersih, dibulatkan ke atas. */
    public static function thresholdAmount(int $netBill, int $percent): int
    {
        return intdiv($percent * $netBill + 99, 100);
    }

    /**
     * Beri hak bila murid mencapai ambang, atau cabut hak bila pembayaran sah turun di bawah ambang
     * saat hak diberikan DAN belum ada seragam yang diambil.
     * Hak yang sudah ada tidak dicabut karena ambang tahun ajaran dinaikkan.
     */
    public function sync(Applicant $applicant): void
    {
        $applicant = Applicant::withTrashed()->with(['billing', 'uniformEntitlement', 'academicYear'])->find($applicant->id);
        if (! $applicant) {
            return;
        }

        $paid = (int) $applicant->payments()->whereNull('cancelled_at')->sum('amount');
        $entitlement = $applicant->uniformEntitlement;

        if ($entitlement) {
            if (($paid <= 0 || $paid < $entitlement->threshold_amount) && ! $applicant->uniformHandovers()->exists()) {
                $entitlement->delete();

                ActivityLog::record(
                    'uniform.entitlement_revoked',
                    "Hak seragam {$applicant->registration_number} dicabut karena pembayaran sah di bawah ambang",
                    $applicant,
                    ['hak' => 'berhak', 'ambang' => $entitlement->threshold_amount],
                    ['hak' => 'dicabut', 'total_dibayar' => $paid],
                );
            }

            return;
        }

        if ($applicant->withdrawn_at || ! $applicant->billing || $paid <= 0) {
            return;
        }

        $percent = (int) $applicant->academicYear->uniform_threshold_percent;
        $amount = self::thresholdAmount($applicant->billing->net_bill, $percent);

        if ($paid < $amount) {
            return;
        }

        UniformEntitlement::create([
            'applicant_id'      => $applicant->id,
            'academic_year_id'  => $applicant->academic_year_id,
            'threshold_percent' => $percent,
            'threshold_amount'  => $amount,
            'net_bill'          => $applicant->billing->net_bill,
            'granted_at'        => now(),
        ]);

        ActivityLog::record(
            'uniform.entitled',
            "{$applicant->registration_number} berhak menerima seragam",
            $applicant,
            null,
            ['ambang' => $percent.'%', 'ambang_rupiah' => $amount, 'total_dibayar' => $paid],
        );
    }

    /** Evaluasi semua murid tahun ajaran (misalnya setelah ambang diturunkan). Mengembalikan jumlah hak baru. */
    public function syncYear(AcademicYear $year): int
    {
        $before = UniformEntitlement::where('academic_year_id', $year->id)->count();

        Applicant::query()
            ->where('academic_year_id', $year->id)
            ->whereNull('withdrawn_at')
            ->whereHas('billing')
            ->whereDoesntHave('uniformEntitlement')
            ->chunkById(200, fn ($applicants) => $applicants->each(fn ($a) => $this->sync($a)));

        return UniformEntitlement::where('academic_year_id', $year->id)->count() - $before;
    }

    /**
     * @return array{status: string, entitled: bool, paid: int, net: ?int, required: ?int, shortage: ?int, entitlement: ?UniformEntitlement, warnings: list<string>}
     */
    public function state(Applicant $applicant): array
    {
        $applicant->loadMissing(['billing', 'payments', 'uniformEntitlement', 'uniformHandovers.type', 'academicYear']);

        $paid = (int) $applicant->payments->filter(fn ($p) => $p->cancelled_at === null)->sum('amount');
        $net = $applicant->billing?->net_bill;
        $required = $net === null ? null : self::thresholdAmount($net, (int) $applicant->academicYear->uniform_threshold_percent);
        $entitlement = $applicant->uniformEntitlement;
        $handovers = $applicant->uniformHandovers;
        $warnings = [];

        if ($entitlement && $handovers->isNotEmpty() && $paid < $entitlement->threshold_amount) {
            $warnings[] = 'Total pembayaran sah kini Rp '.number_format($paid, 0, ',', '.').', di bawah ambang hak seragam (Rp '
                .number_format($entitlement->threshold_amount, 0, ',', '.').'), padahal seragam sudah diambil. Urus di luar sistem.';
        }
        if ($applicant->withdrawn_at && $handovers->isNotEmpty()) {
            $warnings[] = 'Murid mengundurkan diri tetapi seragam sudah diambil ('.$handovers->map(fn ($h) => $h->type->name)->join(', ').'). Pengembalian seragam diurus di luar sistem.';
        }

        $status = match (true) {
            $applicant->withdrawn_at !== null => 'mundur',
            $entitlement !== null             => 'berhak',
            $paid <= 0                        => 'belum_bayar',
            default                           => 'belum_berhak',
        };

        return [
            'status'      => $status,
            'entitled'    => $entitlement !== null,
            'paid'        => $paid,
            'net'         => $net,
            'required'    => $required,
            'shortage'    => $required === null ? null : max(0, $required - $paid),
            'entitlement' => $entitlement,
            'warnings'    => $warnings,
        ];
    }

    public function handOver(Applicant $applicant, UniformType $type, User $by): UniformHandover
    {
        $this->ensureActiveYear($applicant);

        try {
            return DB::transaction(function () use ($applicant, $type, $by) {
                $applicant = Applicant::lockForUpdate()->findOrFail($applicant->id);

                if ($applicant->withdrawn_at) {
                    throw new DomainException('Murid sudah mengundurkan diri. Seragam tidak bisa diserahkan.');
                }
                if (! $applicant->uniformEntitlement()->exists()) {
                    throw new DomainException('Murid belum berhak menerima seragam.');
                }
                $this->ensureUsableType($applicant, $type);

                if (UniformHandover::where('applicant_id', $applicant->id)->where('uniform_type_id', $type->id)->exists()) {
                    throw new DomainException("{$type->name} sudah diserahkan.");
                }

                $sizeId = null;
                if ($type->needs_size) {
                    $sizeId = ApplicantUniformSize::where('applicant_id', $applicant->id)->where('uniform_type_id', $type->id)->value('uniform_size_id');
                    if (! $sizeId) {
                        throw new DomainException("Catat ukuran {$type->name} lebih dulu sebelum menyerahkan.");
                    }
                }

                $handover = UniformHandover::create([
                    'applicant_id' => $applicant->id, 'uniform_type_id' => $type->id, 'uniform_size_id' => $sizeId,
                    'handed_at' => now(), 'handed_by' => $by->id,
                ]);

                ActivityLog::record(
                    'uniform.handed_over',
                    "{$type->name} diserahkan kepada {$applicant->registration_number} {$applicant->name}",
                    $applicant,
                    null,
                    array_filter(['seragam' => $type->name, 'ukuran' => $sizeId ? UniformSize::whereKey($sizeId)->value('name') : null]),
                );

                return $handover;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException("{$type->name} sudah diserahkan.");
        }
    }

    /** Panitia seragam hanya pada hari penyerahan, admin kapan saja. Di luar hari penyerahan alasan wajib. */
    public function cancelHandover(Applicant $applicant, UniformType $type, ?string $reason, User $by): void
    {
        $this->ensureActiveYear($applicant);

        $handover = UniformHandover::where('applicant_id', $applicant->id)->where('uniform_type_id', $type->id)->first();
        if (! $handover) {
            throw new DomainException("{$type->name} belum diserahkan.");
        }

        $sameDay = $handover->handed_at->isSameDay(now());
        if (! $sameDay && ! $by->isAdmin()) {
            throw new DomainException('Pembatalan di luar hari penyerahan hanya bisa dilakukan admin.');
        }
        if (! $sameDay && trim((string) $reason) === '') {
            throw new DomainException('Alasan wajib diisi untuk pembatalan setelah hari penyerahan.');
        }

        DB::transaction(function () use ($handover, $applicant, $type, $reason) {
            $handover->delete();

            ActivityLog::record(
                'uniform.handover_cancelled',
                "Penyerahan {$type->name} kepada {$applicant->registration_number} dibatalkan",
                $applicant,
                ['seragam' => $type->name, 'status' => 'diserahkan'],
                ['seragam' => $type->name, 'status' => 'dibatalkan'],
                trim((string) $reason) ?: null,
            );

            $this->sync($applicant); // bila pembayaran sudah di bawah ambang dan tidak ada seragam lagi, hak dicabut
        });
    }

    public function setSize(Applicant $applicant, UniformType $type, UniformSize $size, User $by): void
    {
        $this->ensureActiveYear($applicant);

        if ($applicant->withdrawn_at) {
            throw new DomainException('Murid sudah mengundurkan diri.');
        }
        if (! $applicant->uniformEntitlement()->exists()) {
            throw new DomainException('Ukuran baru bisa dicatat setelah murid berhak menerima seragam.');
        }
        if (! $type->needs_size) {
            throw new DomainException("{$type->name} tidak memakai ukuran.");
        }
        $this->ensureUsableType($applicant, $type, allowInactive: true);

        $existing = ApplicantUniformSize::where('applicant_id', $applicant->id)->where('uniform_type_id', $type->id)->first();
        if (! $size->is_active && $existing?->uniform_size_id !== $size->id) {
            throw new DomainException('Ukuran tersebut sudah tidak aktif.');
        }

        $handover = UniformHandover::where('applicant_id', $applicant->id)->where('uniform_type_id', $type->id)->first();
        if ($handover && ! $by->isAdmin()) {
            throw new DomainException("{$type->name} sudah diambil. Hanya admin yang dapat mengubah ukurannya.");
        }

        DB::transaction(function () use ($applicant, $type, $size, $by, $existing, $handover) {
            $oldName = $existing ? UniformSize::whereKey($existing->uniform_size_id)->value('name') : null;

            ApplicantUniformSize::updateOrCreate(
                ['applicant_id' => $applicant->id, 'uniform_type_id' => $type->id],
                ['uniform_size_id' => $size->id, 'set_by' => $by->id],
            );
            $handover?->update(['uniform_size_id' => $size->id]);

            if ($oldName !== $size->name) {
                ActivityLog::record(
                    'uniform.size_set',
                    "Ukuran {$type->name} {$applicant->registration_number} dicatat",
                    $applicant,
                    ['ukuran' => $oldName],
                    ['ukuran' => $size->name],
                );
            }
        });
    }

    private function ensureUsableType(Applicant $applicant, UniformType $type, bool $allowInactive = false): void
    {
        if ($type->academic_year_id !== $applicant->academic_year_id || (! $allowInactive && ! $type->is_active)) {
            throw new DomainException('Jenis seragam tidak aktif untuk tahun ajaran ini.');
        }
    }

    private function ensureActiveYear(Applicant $applicant): void
    {
        if (! AcademicYear::whereKey($applicant->academic_year_id)->where('is_active', true)->exists()) {
            throw new DomainException('Data tahun ajaran arsip hanya bisa dilihat.');
        }
    }
}
