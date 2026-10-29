@props(['selected' => null])
@php
    $selectedId = old('referrer_id', $selected?->id);
    $selectedName = $selectedId ? \App\Models\Referrer::find($selectedId)?->name : null;
@endphp

<div data-referrer-picker data-search-url="{{ route('referrers.search') }}" data-store-url="{{ route('referrers.store') }}" class="relative">
    <input type="hidden" name="referrer_id" value="{{ $selectedId }}">
    <div class="flex gap-2">
        <input type="text" autocomplete="off" value="{{ $selectedName }}" placeholder="Ketik nama pembawa (opsional)"
               class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        <button type="button" data-clear class="rounded border px-3 py-2 text-sm hover:bg-slate-50">Kosongkan</button>
    </div>
    <ul data-list class="absolute z-10 mt-1 hidden max-h-60 w-full overflow-auto rounded border bg-white text-sm shadow"></ul>
    <p data-status class="mt-1 text-xs text-slate-500"></p>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-referrer-picker]').forEach(function (root) {
        var hidden = root.querySelector('input[type=hidden]');
        var input = root.querySelector('input[type=text]');
        var list = root.querySelector('[data-list]');
        var status = root.querySelector('[data-status]');
        var form = root.closest('form');
        var timer = null;
        var chosenName = input.value;

        function csrf() {
            var t = form && form.querySelector('input[name=_token]');
            return t ? t.value : '';
        }
        function closeList() { list.classList.add('hidden'); list.innerHTML = ''; }
        function choose(id, name, note) {
            hidden.value = id; input.value = name; chosenName = name;
            status.textContent = note || '';
            closeList();
        }
        function addRow(text, handler, muted) {
            var li = document.createElement('li');
            li.textContent = text;
            li.className = 'cursor-pointer px-3 py-2 hover:bg-slate-100' + (muted ? ' text-slate-600 border-t' : '');
            li.addEventListener('mousedown', function (e) { e.preventDefault(); handler(); });
            list.appendChild(li);
        }

        async function createReferrer(name, force) {
            status.textContent = 'Menyimpan pembawa baru...';
            var response = await fetch(root.dataset.storeUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ name: name, force: !!force })
            });
            var data = await response.json().catch(function () { return {}; });

            if (response.status === 201) { choose(data.id, data.name, 'Pembawa baru ditambahkan.'); return; }
            if (response.status === 422 && data.existing) { choose(data.existing.id, data.existing.name, 'Nama ini sudah ada, dipilih yang sudah ada.'); return; }
            if (response.status === 409 && data.requires_confirmation) {
                var names = data.similar.map(function (s) { return s.name; }).join(', ');
                if (confirm('Nama mirip dengan: ' + names + '.\nJika orangnya sama, pilih nama yang sudah ada.\nTetap tambahkan sebagai orang berbeda?')) {
                    return createReferrer(name, true);
                }
                status.textContent = 'Dibatalkan. Pilih dari daftar bila orangnya sama.';
                return;
            }
            var msg = data.errors && data.errors.name ? data.errors.name[0] : (data.message || 'Gagal menyimpan pembawa.');
            status.textContent = msg;
        }

        async function search() {
            var q = input.value.trim();
            if (q === '') { closeList(); return; }
            try {
                var response = await fetch(root.dataset.searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                var items = await response.json();
                list.innerHTML = '';
                var exact = false;
                items.forEach(function (item) {
                    if (item.name.toLowerCase() === q.toLowerCase()) { exact = true; }
                    addRow(item.name, function () { choose(item.id, item.name, ''); });
                });
                if (!exact) {
                    addRow('+ Tambah "' + q + '" sebagai pembawa baru', function () { createReferrer(q, false); }, true);
                }
                list.classList.remove('hidden');
            } catch (e) { status.textContent = 'Gagal mencari pembawa.'; }
        }

        input.addEventListener('input', function () {
            if (hidden.value && input.value !== chosenName) {
                hidden.value = '';
                status.textContent = 'Pilih dari daftar atau tambah baru.';
            }
            clearTimeout(timer);
            timer = setTimeout(search, 250);
        });
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
        input.addEventListener('blur', function () { setTimeout(closeList, 150); });
        root.querySelector('[data-clear]').addEventListener('click', function () {
            hidden.value = ''; input.value = ''; chosenName = ''; status.textContent = ''; closeList();
        });
    });
});
</script>
@endonce
