<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Payment;
use App\Services\DocumentChecklist;
use App\Services\EnrollmentService;
use App\Support\DateInput;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $enrollment, private DocumentChecklist $checklist)
    {
    }

    public function index(Request $request): View
    {
        $year = AcademicYear::active();

        $applicants = Applicant::query()
            ->with(['school', 'billing', 'payments' => fn ($q) => $q->whereNull('cancelled_at')])
            ->when($year, fn ($q, $y) => $q->where('academic_year_id', $y->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('registration_number', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->enrollmentStatus($s))
            ->orderByDesc('registration_seq')
            ->paginate(25)
            ->withQueryString();

        $summaries = $applicants->mapWithKeys(fn ($a) => [$a->id => $this->enrollment->summary($a)]);

        return view('enrollment.index', ['applicants' => $applicants, 'summaries' => $summaries, 'year' => $year]);
    }

    public function show(Applicant $applicant): View
    {
        $applicant->load(['school', 'referrer', 'billing', 'academicYear', 'documents', 'familyDetail']);
        $payments = $applicant->payments()->with(['receiver', 'canceller'])->orderBy('id')->get();
        $applicant->setRelation('payments', $payments);

        return view('enrollment.show', [
            'applicant' => $applicant,
            'payments'  => $payments,
            'summary'   => $this->enrollment->summary($applicant),
            'info'      => $this->enrollment->billingFor($applicant),
            'missing'   => $this->checklist->missing($this->checklist->statusMap($applicant), $this->checklist->absentParents($applicant)),
            'canWrite'  => $applicant->academicYear->is_active,
        ]);
    }

    public function storePayment(Request $request, Applicant $applicant): RedirectResponse
    {
        $request->merge(['amount' => preg_replace('/\D/', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'note'   => ['nullable', 'string', 'max:150'],
        ], [], ['amount' => 'nominal', 'note' => 'catatan']);

        try {
            $payment = $this->enrollment->addPayment($applicant, (int) $data['amount'], $data['note'] ?? null, $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('enrollment.show', $applicant)
            ->with('success', "Pembayaran {$payment->receipt_number} tersimpan.")
            ->with('new_receipt', $payment->id);
    }

    public function cancelPayment(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], [], ['reason' => 'alasan']);

        return $this->attempt(
            fn () => $this->enrollment->cancelPayment($payment, $data['reason'], $request->user()),
            $payment->applicant_id,
            "Pembayaran {$payment->receipt_number} dibatalkan.",
        );
    }

    public function withdraw(Request $request, Applicant $applicant): RedirectResponse
    {
        $request->merge(['date' => DateInput::toIso($request->input('date'))]);
        $data = $request->validate([
            'date'   => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'date.date_format'     => 'Tanggal harus berformat dd/mm/yyyy, misalnya 25/12/2026.',
            'date.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
        ], ['date' => 'tanggal', 'reason' => 'alasan']);

        return $this->attempt(
            fn () => $this->enrollment->withdraw($applicant, $data['date'], $data['reason'], $request->user()),
            $applicant->id,
            'Murid ditandai mengundurkan diri.',
        );
    }

    public function cancelWithdrawal(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], [], ['reason' => 'alasan']);

        return $this->attempt(
            fn () => $this->enrollment->cancelWithdrawal($applicant, $data['reason'], $request->user()),
            $applicant->id,
            'Status mundur dibatalkan.',
        );
    }

    public function setDiscount(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $request->validate([
            'percent' => ['required', 'integer', 'between:0,99'],
            'reason'  => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['percent' => 'persen diskon', 'reason' => 'alasan']);

        return $this->attempt(
            fn () => $this->enrollment->setManualDiscount($applicant, (int) $data['percent'], $data['reason'], $request->user()),
            $applicant->id,
            'Diskon diperbarui.',
        );
    }

    public function refundNote(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $request->validate(['refund_note' => ['nullable', 'string', 'max:1000']], [], ['refund_note' => 'catatan pengembalian']);

        $this->enrollment->setRefundNote($applicant, $data['refund_note'] ?? null);

        return redirect()->route('enrollment.show', $applicant)->with('success', 'Catatan pengembalian disimpan.');
    }

    public function receipt(Payment $payment): View
    {
        $payment->load(['applicant.school', 'applicant.billing', 'applicant.academicYear', 'receiver']);

        return view('enrollment.receipt', ['payment' => $payment, 'applicant' => $payment->applicant]);
    }

    public function proof(Applicant $applicant): View
    {
        $applicant->load(['school', 'billing', 'academicYear']);
        $applicant->setRelation('payments', $applicant->payments()->with('receiver')->orderBy('id')->get());

        return view('enrollment.proof', [
            'applicant' => $applicant,
            'summary'   => $this->enrollment->summary($applicant),
        ]);
    }

    private function attempt(callable $action, int $applicantId, string $successMessage): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return redirect()->route('enrollment.show', $applicantId)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('enrollment.show', $applicantId)->with('success', $successMessage);
    }
}
