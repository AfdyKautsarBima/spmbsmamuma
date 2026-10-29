@extends('layouts.app')
@section('title', $applicant->exists ? 'Ubah Pendaftar' : 'Input Pendaftar')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">
        {{ $applicant->exists ? 'Ubah Pendaftar '.$applicant->registration_number : 'Input Pendaftar' }}
        <span class="text-sm font-normal text-slate-500">TA {{ $year->name }}</span>
    </h1>

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
                <label for="registration_number" class="mb-1 block text-sm font-medium">Nomor pendaftaran</label>
                <div class="flex flex-wrap items-center gap-2">
                    <input id="registration_number" name="registration_number" required autofocus autocomplete="off"
                           value="{{ old('registration_number', $applicant->registration_number) }}" placeholder="A-001"
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
                <label for="name" class="mb-1 block text-sm font-medium">Nama murid</label>
                <input id="name" name="name" required value="{{ old('name', $applicant->name) }}"
                       class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <span class="mb-1 block text-sm font-medium">Jenis kelamin</span>
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
                    <label for="birth_place" class="mb-1 block text-sm font-medium">Tempat lahir</label>
                    <input id="birth_place" name="birth_place" required value="{{ old('birth_place', $applicant->birth_place) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('birth_place')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="birth_date" class="mb-1 block text-sm font-medium">Tanggal lahir</label>
                    <input id="birth_date" name="birth_date" type="date" required max="{{ today()->format('Y-m-d') }}"
                           value="{{ old('birth_date', $applicant->birth_date?->format('Y-m-d')) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('birth_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-medium">Alamat</h2>
            <x-region-picker :levels="4" :required="true"
                :values="$applicant->exists ? [$applicant->province_code, $applicant->regency_code, $applicant->district_code, $applicant->village_code] : []" />
            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <label for="rt" class="mb-1 block text-sm font-medium">RT</label>
                    <input id="rt" name="rt" required inputmode="numeric" maxlength="3" value="{{ old('rt', $applicant->rt) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('rt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="rw" class="mb-1 block text-sm font-medium">RW</label>
                    <input id="rw" name="rw" required inputmode="numeric" maxlength="3" value="{{ old('rw', $applicant->rw) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('rw')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-medium">Orang tua</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="father_name" class="mb-1 block text-sm font-medium">Nama ayah</label>
                    <input id="father_name" name="father_name" required value="{{ old('father_name', $applicant->father_name) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('father_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="mother_name" class="mb-1 block text-sm font-medium">Nama ibu</label>
                    <input id="mother_name" name="mother_name" required value="{{ old('mother_name', $applicant->mother_name) }}"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @error('mother_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="sm:w-1/2">
                <label for="phone" class="mb-1 block text-sm font-medium">Nomor HP (opsional)</label>
                <input id="phone" name="phone" inputmode="tel" value="{{ old('phone', $applicant->phone) }}" placeholder="08xxxxxxxxxx"
                       class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="font-medium">Asal sekolah dan pembawa</h2>
            <div>
                <label for="school_id" class="mb-1 block text-sm font-medium">Asal sekolah</label>
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
                <label class="mb-1 block text-sm font-medium">Pembawa (opsional)</label>
                @if ($applicant->exists && $applicant->referrerLocked() && ! auth()->user()->isAdmin())
                    <p class="rounded border bg-slate-50 px-3 py-2 text-sm">{{ $applicant->referrer?->name ?? '-' }} (terkunci, hubungi admin untuk mengubah)</p>
                    <input type="hidden" name="referrer_id" value="{{ $applicant->referrer_id }}">
                @else
                    <x-referrer-picker :selected="$applicant->referrer" />
                @endif
                @error('referrer_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
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
