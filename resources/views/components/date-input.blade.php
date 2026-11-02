@props(['name', 'value' => null, 'required' => false])

<input type="text" name="{{ $name }}" value="{{ \App\Support\DateInput::display($value) }}"
       placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off"
       pattern="\d{2}/\d{2}/\d{4}" data-date-input
       @required($required)
       {{ $attributes->merge([
           'class' => 'rounded border border-slate-300 bg-white px-3 py-2 text-sm',
           'title' => 'Format: dd/mm/yyyy, misalnya 25/12/2010',
       ]) }}>

@once
<script>
// Mengetik 25122010 otomatis menjadi 25/12/2010.
document.addEventListener('input', function (event) {
    var el = event.target;
    if (!el.matches || !el.matches('[data-date-input]')) { return; }
    var d = el.value.replace(/\D/g, '').slice(0, 8);
    var out = d;
    if (d.length > 4) { out = d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4); }
    else if (d.length > 2) { out = d.slice(0, 2) + '/' + d.slice(2); }
    el.value = out;
});
</script>
@endonce
