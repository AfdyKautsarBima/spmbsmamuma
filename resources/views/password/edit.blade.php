@extends('layouts.app')
@section('title', 'Ganti Password')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Ganti Password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="max-w-md space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf @method('PUT')
        @foreach ([['current_password', 'Password saat ini', 'current-password'], ['password', 'Password baru (minimal 8 karakter)', 'new-password'], ['password_confirmation', 'Ulangi password baru', 'new-password']] as [$name, $label, $ac])
            <div>
                <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                <input id="{{ $name }}" name="{{ $name }}" type="password" required autocomplete="{{ $ac }}"
                       class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </div>
        @endforeach
        <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan</button>
    </form>
@endsection
