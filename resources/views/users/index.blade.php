@extends('layouts.app')
@section('title', 'Akun')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Kelola Akun</h1>
        <a href="{{ route('users.create') }}" class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Tambah Akun</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Cari nama atau username"
               class="rounded border border-slate-300 px-3 py-2 text-sm">
        <select name="role" class="rounded border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua peran</option>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        <button class="rounded border px-4 py-2 text-sm hover:bg-white">Cari</button>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Username</th>
                    <th class="px-4 py-3">Peran</th>
                    <th class="px-4 py-3">Login terakhir</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($users as $user)
                    <tr class="{{ $user->is_active ? '' : 'text-slate-400' }}">
                        <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3">{{ $user->username }}</td>
                        <td class="px-4 py-3">{{ $user->role->label() }}</td>
                        <td class="px-4 py-3">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td class="flex justify-end gap-2 px-4 py-3">
                            <a href="{{ route('users.edit', $user) }}" class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">Ubah</a>
                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('users.toggle-active', $user) }}"
                                      onsubmit="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $user->username }}?')">
                                    @csrf @method('PATCH')
                                    <button class="rounded border px-3 py-1 text-slate-800 hover:bg-slate-50">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
