@extends('layouts.app')
@section('title', 'Log Aktivitas')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Log Aktivitas</h1>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <select name="event" class="rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua kejadian</option>
            @foreach ($events as $event)
                <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
            @endforeach
        </select>
        <select name="user_id" class="rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua pengguna</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }} ({{ $u->username }})</option>
            @endforeach
        </select>
        <x-date-input name="from" :value="request('from')" class="w-36" title="Dari tanggal (dd/mm/yyyy)" />
        <x-date-input name="to" :value="request('to')" class="w-36" title="Sampai tanggal (dd/mm/yyyy)" />
        <input name="q" value="{{ request('q') }}" placeholder="Cari keterangan" class="rounded border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded border px-4 py-2 text-sm hover:bg-white">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Pengguna</th>
                    <th class="px-4 py-3">Kejadian</th>
                    <th class="px-4 py-3">Keterangan</th>
                    <th class="px-4 py-3">Perubahan</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y align-top">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->username ?? '-' }}</td>
                        <td class="px-4 py-3"><code class="text-xs">{{ $log->event }}</code></td>
                        <td class="px-4 py-3">
                            {{ $log->description }}
                            @if ($log->reason)<p class="text-xs text-slate-500">Alasan: {{ $log->reason }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @foreach (($log->new_values ?? []) as $key => $value)
                                <div>
                                    <span class="text-slate-500">{{ $key }}:</span>
                                    @if (array_key_exists($key, $log->old_values ?? []))
                                        <span class="text-red-700 line-through">{{ is_scalar($log->old_values[$key]) || is_null($log->old_values[$key]) ? ($log->old_values[$key] ?? 'kosong') : json_encode($log->old_values[$key]) }}</span> →
                                    @endif
                                    <span class="text-green-700">{{ is_scalar($value) || is_null($value) ? ($value ?? 'kosong') : json_encode($value) }}</span>
                                </div>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada log.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
