@php
    $tabs = [
        ['schools.index', 'Asal Sekolah', 'schools.*'],
        ['referrers.index', 'Pembawa', 'referrers.*'],
        ['occupations.index', 'Pekerjaan', 'occupations.*'],
        ['regions.index', 'Wilayah', 'regions.*'],
    ];
@endphp
<div class="mb-5 flex flex-wrap gap-1 border-b border-slate-300">
    <a href="{{ route('master.index') }}" class="-mb-px border-b-2 px-3 py-2 text-sm {{ request()->routeIs('master.index') ? 'border-slate-800 font-medium' : 'border-transparent text-slate-500 hover:text-slate-800' }}">Ringkasan</a>
    @foreach ($tabs as [$route, $label, $pattern])
        <a href="{{ route($route) }}" class="-mb-px border-b-2 px-3 py-2 text-sm {{ request()->routeIs($pattern) ? 'border-slate-800 font-medium' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $label }}</a>
    @endforeach
</div>
