@extends('layouts.app')
@section('title', $user->exists ? 'Ubah Akun' : 'Tambah Akun')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ $user->exists ? 'Ubah Akun '.$user->username : 'Tambah Akun' }}</h1>

    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
          class="max-w-md space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Nama</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="username" class="mb-1 block text-sm font-medium">Username</label>
            <input id="username" name="username" value="{{ old('username', $user->username) }}" required autocomplete="off"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">Huruf kecil, angka, titik, garis bawah, atau tanda hubung.</p>
        </div>
        <div>
            <label for="role" class="mb-1 block text-sm font-medium">Peran</label>
            @php($self = $user->exists && $user->is(auth()->user()))
            <select id="role" name="role" @disabled($self) class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            @if ($self)
                <input type="hidden" name="role" value="{{ $user->role->value }}">
                <p class="mt-1 text-xs text-slate-500">Peran akun sendiri tidak bisa diubah.</p>
            @endif
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">{{ $user->exists ? 'Password baru (opsional)' : 'Password' }}</label>
            <input id="password" name="password" type="text" autocomplete="off" @required(! $user->exists)
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">
                Minimal 8 karakter.{{ $user->exists ? ' Kosongkan bila tidak ingin mereset password.' : '' }}
                Sampaikan ke pemilik akun dan minta segera menggantinya.
            </p>
        </div>

        <div class="flex gap-2">
            <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
            <a href="{{ route('users.index') }}" class="rounded border px-4 py-2 text-sm hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
