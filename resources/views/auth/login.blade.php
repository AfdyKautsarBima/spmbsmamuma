<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
<div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
    <h1 class="mb-1 text-xl font-semibold text-slate-800">SPMB SMA</h1>
    <p class="mb-5 text-sm text-slate-500">Masuk dengan akun panitia.</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="username" class="mb-1 block text-sm font-medium">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus
                   autocomplete="username" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            @error('username')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1"> Ingat saya
        </label>
        <button class="w-full rounded bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">Masuk</button>
    </form>
    <p class="mt-4 text-xs text-slate-400">Lupa password? Hubungi admin.</p>
</div>
</body>
</html>
