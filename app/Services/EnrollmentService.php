<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\ApplicantBilling;
use App\Models\DiscountPeriod;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh aturan daftar ulang dan pembayaran. Pelanggaran aturan dilempar sebagai DomainException
 * dengan pesan siap tampil.
 */
class EnrollmentService
{
    public static function netBill(int $fee, int $percent): int
    {
        return intdiv($fee * (100 - $percent), 100); // dibulatkan ke bawah
    }

    public function periodFor(int $yearId, CarbonInterface $at): ?DiscountPeriod
    {
        $date = $at->toDateString();

        return DiscountPeriod::query()
            ->where('academic_year_id', $yearId)
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->orderByDesc('percent')
            ->first();
    }

    /**
     * Tagihan yang berlaku untuk murid: tagihan beku bila sudah ada, atau perkiraan untuk pembayaran pertama.
     *
     * @return array{frozen: bool, fee: int, percent: int, label: ?string, type: string, net: int}
     */
    public function billingFor(Applicant $applicant, ?CarbonInterface $at = null): array
    {
        $billing = $applicant->billing;
        if ($billing) {
            return [
                'frozen' => true, 'fee' => $billing->fee_snapshot, 'percent' => $billing->discount_percent,
                'label' => $billing->discount_label, 'type' => $billing->discount_type, 'net' => $billing->net_bill,
            ];
        }

        $fee = (int) $applicant->academicYear()->value('registration_fee');
        $period = $this->periodFor($applicant->academic_year_id, $at ?? now());
        $percent = $period?->percent ?? 0;

        return [
            'frozen' => false, 'fee' => $fee, 'percent' => $percent, 'label' => $period?->name,
            'type' => $period ? 'periode' : 'none', 'net' => self::netBill($fee, $percent),
        ];
    }

    /**
     * @return array{status: EnrollmentStatus, paid: int, net: ?int, remaining: ?int, overpaid: int, has_payments: bool, billing: ?ApplicantBilling}
     */
    public function summary(Applicant $applicant): array
    {
        $payments = $applicant->relationLoaded('payments') ? $applicant->payments : $applicant->payments()->get();
        $paid = (int) $payments->filter(fn ($p) => $p->cancelled_at === null)->sum('amount');
        $billing = $applicant->billing;
        $net = $billing?->net_bill;

        return [
            'status'       => $this->statusFor($applicant->withdrawn_at !== null, $paid, $net),
            'paid'         => $paid,
            'net'          => $net,
            'remaining'    => $net === null ? null : max(0, $net - $paid),
            'overpaid'     => $net === null ? 0 : max(0, $paid - $net),
            'has_payments' => $paid > 0,
            'billing'      => $billing,
        ];
    }

    public function statusFor(bool $withdrawn, int $paid, ?int $net): EnrollmentStatus
    {
        if ($withdrawn) {
            return EnrollmentStatus::Mundur;
        }
        if ($paid === 0) {
            return EnrollmentStatus::Terdaftar;
        }

        return $net !== null && $paid >= $net ? EnrollmentStatus::Lunas : EnrollmentStatus::Menyicil;
    }

    public function addPayment(Applicant $applicant, int $amount, ?string $note, User $by): Payment
    {
        $this->ensureActiveYear($applicant);

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => $this->recordPayment($applicant->id, $amount, $note, $by));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw new DomainException('Nomor kwitansi bentrok. Coba simpan sekali lagi.');
                }
            }
        }
    }

    private function recordPayment(int $applicantId, int $amount, ?string $note, User $by): Payment
    {
        $applicant = Applicant::lockForUpdate()->with('academicYear')->findOrFail($applicantId);

        if ($applicant->withdrawn_at) {
            throw new DomainException('Murid sudah mengundurkan diri. Pembayaran tidak bisa ditambahkan.');
        }
        if ($amount < 1) {
            throw new DomainException('Nominal pembayaran harus lebih dari nol.');
        }

        $billing = $applicant->billing;
        if (! $billing) {
            $billing = $this->freezeBilling($applicant);
        }

        $paid = (int) $applicant->payments()->whereNull('cancelled_at')->sum('amount');
        $remaining = $billing->net_bill - $paid;

        if ($remaining <= 0) {
            throw new DomainException('Tagihan murid ini sudah lunas.');
        }
        if ($amount > $remaining) {
            throw new DomainException('Nominal melebihi sisa tagihan (Rp '.number_format($remaining, 0, ',', '.').').');
        }

        $year = $applicant->academicYear;
        $seq = ((int) Payment::where('academic_year_id', $year->id)->max('receipt_seq')) + 1;

        $payment = Payment::create([
            'applicant_id'     => $applicant->id,
            'academic_year_id' => $year->id,
            'receipt_seq'      => $seq,
            'receipt_number'   => sprintf('KW-%s-%05d', substr($year->name, 0, 4), $seq),
            'amount'           => $amount,
            'paid_at'          => now(),
            'received_by'      => $by->id,
            'note'             => $note,
        ]);

        ActivityLog::record(
            'payment.created',
            "Pembayaran {$payment->receipt_number} Rp ".number_format($amount, 0, ',', '.')." dari {$applicant->registration_number}",
            $applicant,
            null,
            ['kwitansi' => $payment->receipt_number, 'nominal' => $amount, 'total_dibayar' => $paid + $amount, 'tagihan_bersih' => $billing->net_bill],
        );

        return $payment;
    }

    /** Bekukan biaya dan diskon periode (bila ada) saat pembayaran pertama yang sah. */
    private function freezeBilling(Applicant $applicant): ApplicantBilling
    {
        $info = $this->billingFor($applicant);

        if ($info['fee'] <= 0) {
            throw new DomainException('Biaya daftar ulang tahun ajaran ini belum diatur. Minta admin mengisinya di menu Tahun Ajaran.');
        }

        $billing = ApplicantBilling::create([
            'applicant_id'       => $applicant->id,
            'academic_year_id'   => $applicant->academic_year_id,
            'fee_snapshot'       => $info['fee'],
            'discount_percent'   => $info['percent'],
            'discount_label'     => $info['label'],
            'discount_type'      => $info['type'],
            'discount_period_id' => $info['type'] === 'periode' ? $this->periodFor($applicant->academic_year_id, now())?->id : null,
            'net_bill'           => $info['net'],
        ]);

        if ($info['percent'] > 0) {
            ActivityLog::record(
                'discount.applied',
                "Diskon {$info['label']} {$info['percent']}% diterapkan pada {$applicant->registration_number}",
                $applicant,
                null,
                ['diskon' => $info['percent'].'%', 'tagihan_bersih' => $info['net']],
            );
        }

        $applicant->setRelation('billing', $billing);

        return $billing;
    }

    public function cancelPayment(Payment $payment, string $reason, User $by): void
    {
        DB::transaction(function () use ($payment, $reason, $by) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $applicant = Applicant::withTrashed()->with('billing')->findOrFail($payment->applicant_id);

            $this->ensureActiveYear($applicant);

            if ($payment->cancelled_at) {
                throw new DomainException('Pembayaran ini sudah dibatalkan.');
            }

            $payment->update(['cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason]);

            ActivityLog::record(
                'payment.cancelled',
                "Pembayaran {$payment->receipt_number} Rp ".number_format($payment->amount, 0, ',', '.')." dibatalkan ({$applicant->registration_number})",
                $applicant,
                ['kwitansi' => $payment->receipt_number, 'status' => 'sah'],
                ['kwitansi' => $payment->receipt_number, 'status' => 'dibatalkan'],
                $reason,
            );

            // Tidak ada pembayaran sah lagi: murid kembali Terdaftar. Tagihan beku (dan diskon periodenya) ikut dilepas,
            // kecuali diskon manual yang sengaja dipasang admin.
            $stillPaid = $applicant->payments()->whereNull('cancelled_at')->exists();
            $billing = $applicant->billing;

            if (! $stillPaid && $billing && $billing->discount_type !== 'manual') {
                $billing->delete();

                if ($billing->discount_percent > 0) {
                    ActivityLog::record(
                        'discount.removed',
                        "Tanda diskon {$billing->discount_label} dilepas dari {$applicant->registration_number} karena pembayaran pertama dibatalkan",
                        $applicant,
                        ['diskon' => $billing->discount_percent.'%'],
                        null,
                        $reason,
                    );
                }
            }
        });
    }

    /** Diskon manual (persen 0 sampai 99) menggantikan diskon periode. Persen 0 berarti tanpa diskon. */
    public function setManualDiscount(Applicant $applicant, int $percent, string $reason, User $by): void
    {
        $this->ensureActiveYear($applicant);

        if ($percent < 0 || $percent > 99) {
            throw new DomainException('Persen diskon harus antara 0 dan 99.');
        }

        DB::transaction(function () use ($applicant, $percent, $reason, $by) {
            $billing = $applicant->billing;
            $fee = $billing?->fee_snapshot ?? (int) $applicant->academicYear()->value('registration_fee');

            if ($fee <= 0) {
                throw new DomainException('Biaya daftar ulang tahun ajaran ini belum diatur.');
            }

            $old = $billing ? ['diskon' => $billing->discount_percent.'%', 'tagihan_bersih' => $billing->net_bill] : null;
            $net = self::netBill($fee, $percent);

            $attributes = [
                'academic_year_id'   => $applicant->academic_year_id,
                'fee_snapshot'       => $fee,
                'discount_percent'   => $percent,
                'discount_label'     => $percent > 0 ? 'Diskon khusus' : null,
                'discount_type'      => 'manual',
                'discount_period_id' => null,
                'net_bill'           => $net,
                'reason'             => $reason,
                'set_by'             => $by->id,
            ];

            $billing = ApplicantBilling::updateOrCreate(['applicant_id' => $applicant->id], $attributes);

            ActivityLog::record(
                'discount.manual_set',
                "Diskon manual {$percent}% ditetapkan untuk {$applicant->registration_number}",
                $applicant,
                $old,
                ['diskon' => $percent.'%', 'tagihan_bersih' => $billing->net_bill],
                $reason,
            );
        });

        $applicant->unsetRelation('billing');
    }

    public function withdraw(Applicant $applicant, string $date, string $reason, User $by): void
    {
        $this->ensureActiveYear($applicant);

        if ($applicant->withdrawn_at) {
            throw new DomainException('Murid ini sudah ditandai mengundurkan diri.');
        }

        $paid = (int) $applicant->payments()->whereNull('cancelled_at')->sum('amount');
        if ($paid > 0 && ! $by->isAdmin()) {
            throw new DomainException('Murid ini sudah membayar. Hanya admin yang dapat menandainya mengundurkan diri.');
        }

        $applicant->update(['withdrawn_at' => $date, 'withdrawn_reason' => $reason, 'withdrawn_by' => $by->id]);

        ActivityLog::record(
            'enrollment.withdrawn',
            "{$applicant->registration_number} {$applicant->name} mengundurkan diri".($paid > 0 ? ' (sudah membayar Rp '.number_format($paid, 0, ',', '.').')' : ''),
            $applicant,
            null,
            ['tanggal' => $date, 'sudah_dibayar' => $paid],
            $reason,
        );
    }

    public function cancelWithdrawal(Applicant $applicant, string $reason, User $by): void
    {
        $this->ensureActiveYear($applicant);

        if (! $applicant->withdrawn_at) {
            throw new DomainException('Murid ini tidak berstatus mengundurkan diri.');
        }

        $applicant->update(['withdrawn_at' => null, 'withdrawn_reason' => null, 'withdrawn_by' => null]);

        ActivityLog::record(
            'enrollment.withdrawal_cancelled',
            "Status mundur {$applicant->registration_number} dibatalkan",
            $applicant,
            ['status' => 'mengundurkan diri'],
            ['status' => 'aktif'],
            $reason,
        );
    }

    public function setRefundNote(Applicant $applicant, ?string $note): void
    {
        $old = $applicant->refund_note;
        $applicant->update(['refund_note' => $note]);

        if ($old !== $note) {
            ActivityLog::record(
                'enrollment.refund_note',
                "Catatan pengembalian {$applicant->registration_number} diperbarui",
                $applicant,
                ['catatan' => $old],
                ['catatan' => $note],
            );
        }
    }

    private function ensureActiveYear(Applicant $applicant): void
    {
        if (! AcademicYear::whereKey($applicant->academic_year_id)->where('is_active', true)->exists()) {
            throw new DomainException('Data tahun ajaran arsip hanya bisa dilihat.');
        }
    }
}
