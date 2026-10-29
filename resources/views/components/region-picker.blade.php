@props(['levels' => 4, 'values' => [], 'required' => false])
@php
    $keys = ['province_code', 'regency_code', 'district_code', 'village_code'];
    $labels = ['Provinsi', 'Kabupaten/Kota', 'Kecamatan', 'Desa/Kelurahan'];
    $preset = [];
    foreach ($keys as $i => $key) {
        $preset[$i] = old($key, $values[$i] ?? null);
    }
    if (! array_filter($preset)) {
        $preset[0] = config('spmb.default_province_code');
    }
@endphp

<div data-region-picker data-url="{{ route('regions.children') }}" data-levels="{{ $levels }}"
     data-values="{{ json_encode($preset) }}" class="grid gap-3 sm:grid-cols-2">
    @for ($i = 0; $i < $levels; $i++)
        <div>
            <label class="mb-1 block text-sm font-medium">{{ $labels[$i] }}</label>
            <select name="{{ $keys[$i] }}" @required($required)
                    class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">-- Pilih --</option>
            </select>
            @error($keys[$i])<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endfor
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-region-picker]').forEach(function (root) {
        var url = root.dataset.url;
        var levels = parseInt(root.dataset.levels, 10);
        var selects = Array.prototype.slice.call(root.querySelectorAll('select'), 0, levels);
        var preset = JSON.parse(root.dataset.values || '[]');

        function reset(i) {
            selects[i].innerHTML = '';
            selects[i].appendChild(new Option('-- Pilih --', ''));
            selects[i].disabled = i > 0;
        }

        async function load(i, parent, selected) {
            for (var j = i + 1; j < selects.length; j++) { reset(j); }
            var select = selects[i];
            select.disabled = true;
            select.innerHTML = '';
            select.appendChild(new Option('Memuat...', ''));
            try {
                var response = await fetch(url + (parent ? '?parent=' + encodeURIComponent(parent) : ''), {
                    headers: { 'Accept': 'application/json' }
                });
                var data = await response.json();
                select.innerHTML = '';
                select.appendChild(new Option('-- Pilih --', ''));
                data.forEach(function (item) { select.appendChild(new Option(item.name, item.code)); });
                if (selected) { select.value = selected; }
            } catch (e) {
                select.innerHTML = '';
                select.appendChild(new Option('Gagal memuat', ''));
            }
            select.disabled = false;
            if (select.value && i + 1 < selects.length) {
                await load(i + 1, select.value, preset[i + 1]);
            }
        }

        selects.forEach(function (select, i) {
            select.addEventListener('change', function () {
                for (var j = i + 1; j < selects.length; j++) { reset(j); }
                if (select.value && i + 1 < selects.length) { load(i + 1, select.value, null); }
            });
        });

        load(0, null, preset[0]);
    });
});
</script>
@endonce
