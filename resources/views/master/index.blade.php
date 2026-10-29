@extends('layouts.app')
@section('title', 'Master Data')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Master Data</h1>
    @include('partials.master-tabs')

    @if ($regions === 0)
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Data wilayah belum diimpor. Asal sekolah dan alamat pendaftar membutuhkannya.
            Lihat petunjuk di tab <a href="{{ route('regions.index') }}" class="underline">Wilayah</a>.
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['schools.index', 'Asal Sekolah', $schoolsActive.' aktif dari '.$schoolsTotal],
            ['referrers.index', 'Pembawa', $referrers.' orang'],
            ['occupations.index', 'Pekerjaan', $occupations.' pilihan aktif'],
            ['regions.index', 'Wilayah', number_format($regions, 0, ',', '.').' wilayah'],
        ] as [$route, $label, $info])
            <a href="{{ route($route) }}" class="rounded-lg bg-white p-4 shadow-sm hover:shadow">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-semibold">{{ $info }}</p>
            </a>
        @endforeach
    </div>
@endsection
