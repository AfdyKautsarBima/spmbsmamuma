<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Payment;

/** Angka ringkas daftar ulang dan keuangan untuk dashboard. */
class EnrollmentStats
{
    public function __construct(private EnrollmentService $enrollment)
    {
    }

    public function forYear(AcademicYear $year): array
    {
        $rows = Applicant::query()
            ->where('applicants.academic_year_id', $year->id)
            ->leftJoin('applicant_billings as b', 'b.applicant_id', '=', 'applicants.id')
            ->selectRaw('applicants.id, applicants.withdrawn_at, b.fee_snapshot, b.net_bill,
                (select coalesce(sum(p.amount), 0) from payments p where p.applicant_id = applicants.id and p.cancelled_at is null) as paid')
            ->get();

        $counts = array_fill_keys(array_map(fn ($s) => $s->value, EnrollmentStatus::cases()), 0);
        $money = ['gross' => 0, 'net' => 0, 'paid' => 0, 'remaining' => 0, 'withdrawn_paid' => 0, 'installment_remaining' => 0];
        $overpaid = 0;
        $withdrawnWithPayment = 0;

        foreach ($rows as $row) {
            $paid = (int) $row->paid;
            $net = $row->net_bill === null ? null : (int) $row->net_bill;
            $status = $this->enrollment->statusFor($row->withdrawn_at !== null, $paid, $net);
            $counts[$status->value]++;

            if ($status === EnrollmentStatus::Mundur) {
                $money['withdrawn_paid'] += $paid;
                $withdrawnWithPayment += $paid > 0 ? 1 : 0;
                continue;
            }
            if ($paid === 0 || $net === null) {
                continue;
            }

            $money['gross'] += (int) $row->fee_snapshot;
            $money['net'] += $net;
            $money['paid'] += $paid;
            $money['remaining'] += max(0, $net - $paid);
            $overpaid += $paid > $net ? 1 : 0;
            if ($status === EnrollmentStatus::Menyicil) {
                $money['installment_remaining'] += $net - $paid;
            }
        }

        $today = Payment::query()->where('academic_year_id', $year->id)->whereNull('cancelled_at')->whereDate('paid_at', today());

        return [
            'counts'                 => $counts,
            'money'                  => $money + ['discount' => $money['gross'] - $money['net']],
            'overpaid'               => $overpaid,
            'withdrawn_with_payment' => $withdrawnWithPayment,
            'today_money'            => (int) (clone $today)->sum('amount'),
            'today_students'         => (clone $today)->distinct()->count('applicant_id'),
        ];
    }
}
