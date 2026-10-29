@extends('layouts.app')
@section('title', 'Pembawa')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Master Data</h1>
    @include('partials.master-tabs')

    @if (session('confirm'))
        @php($confirm = session('confirm'))
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">Nama "{{ $confirm['name'] }}" mirip dengan pembawa yang sudah ada:</p>
            <ul class="my-2 ml-5 list-disc">
                @foreach ($confirm['similar'] as $similar)<li>{{ $similar }}</li>@endforeach
            </ul>
            <p class="mb-3">Jika orangnya sama, batalkan dan pakai nama yang sudah ada (atau gabungkan lewat tombol Gabungkan). Jika memang orang berbeda, simpan tetap.</p>
            <form method="POST" action="{{ $confirm['action'] }}" class="flex gap-2">
                @csrf
                @if ($confirm['method'] === 'PUT') @method('PUT') @endif
                <input type="hidden" name="name" value="{{ $confirm['name'] }}">
                <input type="hidden" name="force" value="1">
                <button class="rounded bg-amber-700 px-3 py-1.5 text-white hover:bg-amber-600">Tetap simpan</button>
                <a href="{{ route('referrers.index') }}" class="rounded border border-amber-400 px-3 py-1.5">Batal</a>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('referrers.store') }}" class="mb-4 flex max-w-xl gap-2">
        @csrf
        <input name="name" value="{{ old('name') }}" required placeholder="Nama pembawa baru"
               class="flex-1 rounded border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Tambah</button>
    </form>

    <form method="GET" class="mb-4 flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Cari nama pembawa"
               class="rounded border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded border px-4 py-2 text-sm hover:bg-white">Cari</button>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nama pembawa</th>
                    <th class="px-4 py-3 text-right">Pendaftar TA aktif</th>
                    <th class="px-4 py-3 text-right">Semua tahun</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($referrers as $referrer)
                    <tr>
                        <td class="px-4 py-2">
                            <form method="POST" action="{{ route('referrers.update', $referrer) }}" class="flex gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $referrer->name }}" required class="w-64 rounded border border-slate-300 px-2 py-1">
                                <button class="rounded border px-3 py-1 hover:bg-slate-50">Ubah nama</button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-right">{{ $referrer->year_count }}</td>
                        <td class="px-4 py-2 text-right">{{ $referrer->total_count }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('referrers.merge.form', $referrer) }}" class="rounded border px-3 py-1 hover:bg-slate-50">Gabungkan</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Belum ada pembawa. Pembawa juga bisa ditambah langsung dari form pendaftaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $referrers->links() }}</div>
@endsection
