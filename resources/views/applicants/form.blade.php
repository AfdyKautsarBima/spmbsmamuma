@extends('layouts.app')
@section('title', $applicant->exists ? 'Ubah Pendaftar' : 'Input Pendaftar')

@section('content')
    @php
        $referrerClassOptions = \App\Support\ReferrerClass::OPTIONS;
        $storedReferrerClass = old('referrer_class_name', $applicant->referrer_class_name);
        $selectedReferrerClass = old(
            'referrer_class_option',
            in_array($storedReferrerClass, $referrerClassOptions, true)
                ? $storedReferrerClass
                : ($storedReferrerClass ? \App\Support\ReferrerClass::OTHER : '')
        );
        $otherReferrerClass = old(
            'referrer_class_other',
            $selectedReferrerClass === \App\Support\ReferrerClass::OTHER ? $storedReferrerClass : ''
        );
    @endphp
    <h1 class="mb-4 text-xl font-semibold">
        {{ $applicant->exists ? 'Ubah Pendaftar '.$applicant->registration_number : 'Input Pendaftar' }}
        <span class="text-sm font-normal text-slate-500">TA {{ $year->name }}</span>
    </h1>
    <p class="mb-4 text-sm text-slate-600"><span class="text-red-600">*</span> wajib diisi; kolom lainnya opsional.</p>

    @if (session('saved'))
        @php($saved = session('saved'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">
            Tersimpan: <strong>{{ $saved['number'] }}</strong> {{ $saved['name'] }}.
            <a href="{{ route('applicants.print', $saved['id']) }}" target="_blank" class="underline">Cetak bukti</a> |
            <a href="{{ route('applicants.show', $saved['id']) }}" class="underline">Lihat data</a>
        </div>
    @endif

    @if (session('warnings'))
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="mb-2 font-medium">Periksa dulu sebelum menyimpan:</p>
            <ul class="mb-3 space-y-2">
                @foreach (session('warnings') as $warning)
                    <li>
                        {{ $warning['message'] }}
                        @foreach ($warning['applicants'] as $other)
                            <div class="ml-4">
                                <a href="{{ route('applicants.show', $other['id']) }}" target="_blank" class="underline">{{ $other['number'] }} {{ $other['name'] }}</a>
                            </div>
                        @endforeach
                    </li>
                @endforeach
            </ul>
            <p>Jika data sudah benar, tekan <strong>Tetap simpan</strong> di bagian bawah form.</p>
        </div>
    @endif

    @php($hasWarnings = (bool) session('warnings'))

    <form method="POST" action="{{ $applicant->exists ? route('applicants.update', $applicant) : route('applicants.store') }}"
          class="max-w-3xl space-y-6">
        @csrf
        @if ($applicant->exists) @method('PUT') @endif

        <section class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-medium">Pendaftaran</h2>
            <div>
                <label for="registration_number" class="mb-1 block text-sm font-medium">Nomor pendaftaran (otomatis, opsional)</label>
                <div class="flex flex-wrap items-center gap-2">
                    <input id="registration_number" name="registration_number" autofocus autocomplete="off"
                           value="{{ old('registration_number', $applicant->registration_number ?: ($hint['next'] ?? '')) }}" placeholder="A-001"
                           class="w-40 rounded border border-slate-300 px-3 py-2 text-sm uppercase">
                    @if ($hint)
                        <span class="text-sm text-slate-500">
                            Nomor terakhir: <strong>{{ $hint['last'] ?? 'belum ada' }}</strong>. Berikutnya: <strong>{{ $hint['next'] }}</strong>
                        </span>
                        <button type="button" class="rounded border px-3 py-1 text-sm hover:bg-slate-50"
                                onclick="document.getElementById('registration_number').value='{{ $hint['next'] }}'">Pakai {{ $hint['next'] }}</button>
                    @endif
                </div>
                @error('registration_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-medium">Identitas murid</h2>
            <div>
                <label for="name" class="mb-1 block text-sm font-medium">Nama pendaftar <span class="text-red-600">*</span></label>
                <input id="name" name="name" required value="{{ old('name', $applicant->name) }}"
                       class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <span class="mb-1 block text-sm font-medium">Jenis kelamin <span class="text-red-600">*</span></span>
                <div class="flex gap-4 text-sm">
                    @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $value => $label)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="gender" value="{{ $value }}" required @checked(old('gender', $applicant->gender) === $value)> {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('gender')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="birth_place" class="mb-1 block text-sm font-medium">Tempat lahir <span class="font-normal text-slate-500">(opsional)</span></label>
                    <input id="birth_place" name="birth_place" value="{{ old('birth_place', $applicant->birth_place) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('birth_place')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="birth_date" class="mb-1 block text-sm font-medium">Tanggal lahir <span class="font-normal text-slate-500">(opsional)</span></label>
                    <x-date-input id="birth_date" name="birth_date" class="w-full"
                                  :value="old('birth_date', $applicant->birth_date?->format('d/m/Y'))" />
                    @error('birth_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="sm:w-1/2">
                <label for="student_phone" class="mb-1 block text-sm font-medium">Nomor HP siswa (opsional)</label>
                <input id="student_phone" name="student_phone" inputmode="tel" maxlength="13"
                       value="{{ old('student_phone', $applicant->student_phone) }}" placeholder="08xxxxxxxxxx"
                       class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('student_phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-medium">Alamat</h2>
            <x-region-picker :levels="4"
                :values="$applicant->exists ? [$applicant->province_code, $applicant->regency_code, $applicant->district_code, $applicant->village_code] : []" />
            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <label for="rt" class="mb-1 block text-sm font-medium">RT <span class="font-normal text-slate-500">(opsional)</span></label>
                    <input id="rt" name="rt" inputmode="numeric" maxlength="3" value="{{ old('rt', $applicant->rt) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('rt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="rw" class="mb-1 block text-sm font-medium">RW <span class="font-normal text-slate-500">(opsional)</span></label>
                    <input id="rw" name="rw" inputmode="numeric" maxlength="3" value="{{ old('rw', $applicant->rw) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('rw')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-medium">Orang tua</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="father_name" class="mb-1 block text-sm font-medium">Nama ayah <span class="font-normal text-slate-500">(opsional)</span></label>
                    <input id="father_name" name="father_name" value="{{ old('father_name', $applicant->father_name) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('father_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="mother_name" class="mb-1 block text-sm font-medium">Nama ibu <span class="font-normal text-slate-500">(opsional)</span></label>
                    <input id="mother_name" name="mother_name" value="{{ old('mother_name', $applicant->mother_name) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('mother_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="sm:w-1/2">
                <label for="phone" class="mb-1 block text-sm font-medium">Nomor HP orang tua/wali (opsional)</label>
                <input id="phone" name="phone" inputmode="tel" value="{{ old('phone', $applicant->phone) }}" placeholder="08xxxxxxxxxx"
                       class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-medium">Asal sekolah dan pembawa</h2>
            <div>
                <label for="school_id" class="mb-1 block text-sm font-medium">Asal sekolah <span class="text-red-600">*</span></label>
                <input type="text" id="school-filter" placeholder="Ketik untuk menyaring daftar sekolah"
                       class="mb-2 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <select id="school_id" name="school_id" required class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">-- Pilih sekolah --</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected((string) old('school_id', $applicant->school_id) === (string) $school->id)>{{ $school->name }} ({{ $school->regency_name }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Sekolah tidak ada di daftar? Minta admin menambahkannya di Master Data.</p>
                @error('school_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="referrer_name" class="mb-1 block text-sm font-medium">Nama pembawa (opsional)</label>
                @if ($applicant->exists && $applicant->referrerLocked() && ! auth()->user()->isAdmin())
                    <p class="rounded border bg-slate-50 px-3 py-2 text-sm">
                        {{ $applicant->referrer_name ?: '-' }}{{ $applicant->referrer_class_name ? ' ('.$applicant->referrer_class_name.')' : '' }}
                        (terkunci, hubungi admin untuk mengubah)
                    </p>
                    <input type="hidden" name="referrer_name" value="{{ $applicant->referrer_name }}">
                    <input type="hidden" name="referrer_class_name" value="{{ $applicant->referrer_class_name }}">
                @else
                    <input id="referrer_name" name="referrer_name" maxlength="100"
                           value="{{ old('referrer_name', $applicant->referrer_name) }}" placeholder="Nama pembawa atau kode referral"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <label for="referrer_class_option" class="mb-1 mt-3 block text-sm font-medium">Kelas pembawa (opsional)</label>
                    <select id="referrer_class_option" name="referrer_class_option"
                            class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">-- Pilih kelas --</option>
                        @foreach ($referrerClassOptions as $className)
                            <option value="{{ $className }}" @selected($selectedReferrerClass === $className)>{{ $className }}</option>
                        @endforeach
                        <option value="{{ \App\Support\ReferrerClass::OTHER }}" @selected($selectedReferrerClass === \App\Support\ReferrerClass::OTHER)>Lainnya</option>
                    </select>
                    <div id="referrer_class_other_wrap" class="mt-2 {{ $selectedReferrerClass === \App\Support\ReferrerClass::OTHER ? '' : 'hidden' }}">
                        <label for="referrer_class_other" class="mb-1 block text-sm font-medium">Kelas lainnya</label>
                        <input id="referrer_class_other" name="referrer_class_other" maxlength="50"
                               value="{{ $otherReferrerClass }}" placeholder="Masukkan kelas pembawa"
                               class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Data pembawa dicatat langsung pada pendaftar ini.</p>
                @endif
                @error('referrer_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('referrer_class_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('referrer_class_option')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('referrer_class_other')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <button class="rounded bg-slate-800 px-5 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            @if ($hasWarnings)
                <button name="confirm_warnings" value="1" class="rounded bg-amber-700 px-5 py-2 text-sm text-white hover:bg-amber-600">Tetap simpan</button>
            @endif
            <a href="{{ $applicant->exists ? route('applicants.show', $applicant) : route('applicants.index') }}" class="rounded border px-5 py-2 text-sm hover:bg-white">Batal</a>
        </div>
    </form>

    <script>
        (function () {
            var classOption = document.getElementById('referrer_class_option');
            var classOtherWrap = document.getElementById('referrer_class_other_wrap');
            var classOther = document.getElementById('referrer_class_other');
            if (classOption && classOtherWrap && classOther) {
                var updateOtherClass = function () {
                    var isOther = classOption.value === @json(\App\Support\ReferrerClass::OTHER);
                    classOtherWrap.classList.toggle('hidden', !isOther);
                };
                classOption.addEventListener('change', updateOtherClass);
                updateOtherClass();
            }

            var filter = document.getElementById('school-filter');
            var select = document.getElementById('school_id');
            filter.addEventListener('input', function () {
                var q = filter.value.toLowerCase();
                Array.prototype.forEach.call(select.options, function (option) {
                    if (!option.value) { return; }
                    option.hidden = q !== '' && option.text.toLowerCase().indexOf(q) === -1;
                });
            });
        })();
    </script>
@endsection
