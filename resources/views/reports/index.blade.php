@extends('layouts.app')
@section('title', 'Laporan')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Laporan</h1>
    <p class="mb-5 max-w-2xl text-sm text-slate-600">
        Setiap laporan bisa dilihat di layar, diunduh sebagai Excel (.xlsx), atau dicetak / disimpan sebagai PDF dari dialog cetak browser.
    </p>

    @forelse ($groups as $group => $reports)
        <h2 class="mb-2 mt-5 text-sm font-medium text-slate-500">{{ $group }}</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($reports as $report)
                <a href="{{ route('reports.show', $report['key']) }}" class="rounded-lg bg-white p-4 shadow-sm hover:shadow">
                    <p class="font-medium">{{ $report['title'] }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $report['description'] }}</p>
                </a>
            @endforeach
        </div>
    @empty
        <p class="text-sm text-slate-500">Tidak ada laporan untuk peran Anda.</p>
    @endforelse
@endsection
