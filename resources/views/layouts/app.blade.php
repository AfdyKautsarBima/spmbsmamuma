<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Beranda') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
@php
    $me = auth()->user();
    $activeYear = \App\Models\AcademicYear::active();
    $link = fn (string $route, string $label, string|array $pattern) =>
        '<a href="'.route($route).'" class="rounded px-3 py-2 text-sm '.(request()->routeIs(...(array) $pattern) ? 'bg-slate-700 text-white' : 'text-slate-200 hover:bg-slate-700').'">'.e($label).'</a>';
    $canRegister = in_array($me->role, [\App\Enums\Role::Admin, \App\Enums\Role::Pendaftaran], true);
@endphp

<header class="bg-slate-800">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-2 px-4 py-3">
        <span class="mr-4 font-semibold text-white">SPMB SMA</span>
        <nav class="flex flex-wrap gap-1">
            {!! $link('dashboard', 'Dashboard', 'dashboard') !!}
            @if ($canRegister)
                {!! $link('applicants.index', 'Pendaftar', 'applicants.*') !!}
            @endif
            @if ($me->isAdmin())
                {!! $link('academic-years.index', 'Tahun Ajaran', 'academic-years.*') !!}
                {!! $link('master.index', 'Master Data', ['master.*', 'schools.*', 'referrers.*', 'occupations.*', 'regions.*']) !!}
                {!! $link('users.index', 'Akun', 'users.*') !!}
                {!! $link('activity-logs.index', 'Log Aktivitas', 'activity-logs.*') !!}
            @endif
        </nav>
        <div class="ml-auto flex items-center gap-3 text-sm text-slate-200">
            <span class="rounded bg-slate-700 px-2 py-1">TA {{ $activeYear?->name ?? '-' }}</span>
            <a href="{{ route('password.edit') }}" class="hover:underline">{{ $me->name }} ({{ $me->role->label() }})</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded bg-slate-600 px-3 py-1 text-white hover:bg-slate-500">Keluar</button>
            </form>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-6">
    @if (! $activeYear && $me->isAdmin())
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Belum ada tahun ajaran aktif. Buat atau aktifkan satu di menu Tahun Ajaran.
        </div>
    @endif

    @include('partials.flash')

    @yield('content')
</main>
</body>
</html>
