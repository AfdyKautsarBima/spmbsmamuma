@extends('layouts.app')
@section('title', 'Cek Berkas '.$applicant->registration_number)

@php
    $hasOld = session()->hasOldInput();
    $fatherAbsent = $hasOld ? (bool) old('family.father_absent') : (bool) $family->father_absent;
    $motherAbsent = $hasOld ? (bool) old('family.mother_absent') : (bool) $family->mother_absent;
    $input = 'w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm';
    $groupHints = [
        'wajib'       => 'Harus berstatus Diterima.',
        'orangtua'    => 'Lengkap bila: KTP ayah dan ibu diterima; atau satu orang tua tidak ada/almarhum (pilih Tidak ada, atau dicentang di data KK) dan KTP orang tua yang lain diterima; atau KTP wali diterima.',
        'kondisional' => 'Putuskan Diterima atau Tidak ada (misalnya murid bukan penerima KKS). Jangan dibiarkan Belum.',
    ];
@endphp

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold">
            Cek Berkas: {{ $applicant->registration_number }} - {{ $applicant->name }}
        </h1>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('applicants.show', $applicant) }}" class="rounded border bg-white px-3 py-2 hover:bg-slate-50">Data pendaftar</a>
            <a href="{{ route('documents.index') }}" class="rounded border bg-white px-3 py-2 hover:bg-slate-50">Kembali ke daftar</a>
        </div>
    </div>

    <p class="mb-4 text-sm text-slate-500">{{ $applicant->school->name }}. Berkas hanya dicentang di sini, tidak diunggah.</p>

    <form method="POST" action="{{ route('documents.update', $applicant) }}" id="docs-form" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        @foreach (\App\Enums\DocumentType::grouped() as $group => $types)
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="font-medium">{{ \App\Enums\DocumentType::GROUP_LABELS[$group] }}</h2>
                <p class="mb-3 text-xs text-slate-500">{{ $groupHints[$group] }}</p>

                <div class="space-y-3">
                    @foreach ($types as $type)
                        @php($row = $documents->get($type->value))
                        <div class="grid items-start gap-2 sm:grid-cols-[1fr_190px]">
                            <div>
                                <label for="doc-{{ $type->value }}" class="text-sm font-medium">{{ $type->label() }}</label>
                                @if ($row?->handled_at)
                                    <p class="text-xs text-slate-400">Diubah {{ $row->handler?->name ?? '-' }}, {{ $row->handled_at->format('d/m/Y H:i') }}</p>
                                @endif
                                @if ($type === \App\Enums\DocumentType::Sertifikat)
                                    <input name="docs[sertifikat][note]" value="{{ old('docs.sertifikat.note', $row?->note) }}"
                                           placeholder="Keterangan, misal: Juara 2 OSN Kabupaten" maxlength="150" class="{{ $input }} mt-1">
                                @endif
                            </div>
                            <select id="doc-{{ $type->value }}" name="docs[{{ $type->value }}][status]" class="{{ $input }}">
                                @foreach ($type->allowedStatuses() as $status)
                                    <option value="{{ $status->value }}" @selected(old("docs.{$type->value}.status", $statuses[$type->value]->value) === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- NISN: muncul saat FC SKL diterima --}}
        <section id="nisn-section" class="rounded-lg bg-white p-5 shadow-sm" hidden>
            <h2 class="font-medium">NISN</h2>
            <p class="mb-3 text-xs text-slate-500">Wajib diisi karena FC SKL diterima. 10 digit angka.</p>
            <input name="nisn" inputmode="numeric" maxlength="10" value="{{ old('nisn', $applicant->nisn) }}" class="{{ $input }} sm:w-64">
        </section>

        {{-- Data KK: muncul saat KK diterima --}}
        <section id="kk-section" class="space-y-5 rounded-lg bg-white p-5 shadow-sm" hidden>
            <div>
                <h2 class="font-medium">Data Kartu Keluarga</h2>
                <p class="text-xs text-slate-500">Wajib diisi karena KK diterima. Isi sesuai KK.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Nomor KK</label>
                    <input name="family[number]" inputmode="numeric" maxlength="16" value="{{ old('family.number', $family->kk_number) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">NIK murid</label>
                    <input name="family[student_nik]" inputmode="numeric" maxlength="16" value="{{ old('family.student_nik', $family->student_nik) }}" class="{{ $input }}">
                </div>
            </div>

            @foreach (['father' => 'Ayah', 'mother' => 'Ibu'] as $parent => $label)
                @php($absent = $parent === 'father' ? $fatherAbsent : $motherAbsent)
                <div class="rounded border border-slate-200 p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-medium">{{ $label }}</h3>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="family[{{ $parent }}_absent]" value="1" @checked($absent)> Tidak ada / almarhum
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="mb-1 block text-sm">Nama {{ strtolower($label) }} (sesuai KK)</label>
                        <input name="family[{{ $parent }}_name]" value="{{ old("family.{$parent}_name", $applicant->{$parent.'_name'}) }}" class="{{ $input }}">
                    </div>
                    <div id="{{ $parent }}-fields" class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm">NIK {{ strtolower($label) }}</label>
                            <input name="family[{{ $parent }}_nik]" inputmode="numeric" maxlength="16" value="{{ old("family.{$parent}_nik", $family->{$parent.'_nik'}) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm">Tahun lahir</label>
                            <input name="family[{{ $parent }}_birth_year]" inputmode="numeric" maxlength="4" value="{{ old("family.{$parent}_birth_year", $family->{$parent.'_birth_year'}) }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm">Pekerjaan</label>
                            <select name="family[{{ $parent }}_occupation_id]" class="{{ $input }}">
                                <option value="">-- Pilih --</option>
                                @foreach ($occupations as $occupation)
                                    <option value="{{ $occupation->id }}" @selected((string) old("family.{$parent}_occupation_id", $family->{$parent.'_occupation_id'}) === (string) $occupation->id)>{{ $occupation->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endforeach

            <div id="guardian-block" class="rounded border border-slate-200 p-4" hidden>
                <h3 class="text-sm font-medium">Wali</h3>
                <p id="guardian-hint" class="mb-3 text-xs text-slate-500"></p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm">Nama wali</label>
                        <input name="family[guardian_name]" value="{{ old('family.guardian_name', $family->guardian_name) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">NIK wali</label>
                        <input name="family[guardian_nik]" inputmode="numeric" maxlength="16" value="{{ old('family.guardian_nik', $family->guardian_nik) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">Hubungan dengan murid</label>
                        <input name="family[guardian_relation]" list="guardian-relations" value="{{ old('family.guardian_relation', $family->guardian_relation) }}" class="{{ $input }}">
                        <datalist id="guardian-relations">
                            @foreach (['Kakek', 'Nenek', 'Paman', 'Bibi', 'Kakak', 'Saudara', 'Lainnya'] as $relation)<option value="{{ $relation }}">@endforeach
                        </datalist>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">Pekerjaan wali</label>
                        <select name="family[guardian_occupation_id]" class="{{ $input }}">
                            <option value="">-- Pilih --</option>
                            @foreach ($occupations as $occupation)
                                <option value="{{ $occupation->id }}" @selected((string) old('family.guardian_occupation_id', $family->guardian_occupation_id) === (string) $occupation->id)>{{ $occupation->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">Nomor HP wali (opsional)</label>
                        <input name="family[guardian_phone]" inputmode="tel" value="{{ old('family.guardian_phone', $family->guardian_phone) }}" class="{{ $input }}">
                    </div>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <button class="rounded bg-slate-800 px-5 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            <button name="back" value="1" class="rounded border bg-white px-5 py-2 text-sm hover:bg-slate-50">Simpan dan kembali ke daftar</button>
        </div>
    </form>

    <script>
        (function () {
            var form = document.getElementById('docs-form');
            var kkStatus = form.querySelector('[name="docs[kk][status]"]');
            var sklStatus = form.querySelector('[name="docs[skl][status]"]');
            var fatherAbsent = form.querySelector('[name="family[father_absent]"]');
            var motherAbsent = form.querySelector('[name="family[mother_absent]"]');

            // Bagian yang disembunyikan juga dinonaktifkan agar tidak ikut terkirim.
            function toggle(el, show) {
                el.hidden = !show;
                el.querySelectorAll('input, select').forEach(function (field) { field.disabled = !show; });
            }

            function refresh() {
                var kk = kkStatus.value === 'diterima';
                toggle(document.getElementById('kk-section'), kk);
                toggle(document.getElementById('nisn-section'), sklStatus.value === 'diterima');

                if (kk) {
                    toggle(document.getElementById('father-fields'), !fatherAbsent.checked);
                    toggle(document.getElementById('mother-fields'), !motherAbsent.checked);

                    var both = fatherAbsent.checked && motherAbsent.checked;
                    toggle(document.getElementById('guardian-block'), fatherAbsent.checked || motherAbsent.checked);
                    document.getElementById('guardian-hint').textContent = both
                        ? 'Wajib diisi karena kedua orang tua tidak ada.'
                        : 'Opsional karena salah satu orang tua masih ada. Kosongkan bila tidak ada wali.';
                }
            }

            [kkStatus, sklStatus, fatherAbsent, motherAbsent].forEach(function (el) {
                el.addEventListener('change', refresh);
            });
            refresh();
        })();
    </script>
@endsection
