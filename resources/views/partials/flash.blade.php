@if (session('success'))
    <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
@endif
@if ($errors->any() && ! request()->routeIs('login'))
    <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
        <p class="font-medium">Data belum bisa disimpan:</p>
        <ul class="ml-5 list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
