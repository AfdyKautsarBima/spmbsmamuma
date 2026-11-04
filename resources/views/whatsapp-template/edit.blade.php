@extends('layouts.app')
@section('title', 'Template WhatsApp')

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Template Pesan WhatsApp</h1>

    <form method="POST" action="{{ route('whatsapp-template.update') }}" class="max-w-2xl space-y-4 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @method('PUT')

        <div>
            <label for="message" class="mb-1 block text-sm font-medium">Isi pesan</label>
            <textarea id="message" name="message" rows="6" maxlength="2000"
                      class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                      placeholder="Yth. orang tua/wali, kami mengingatkan bahwa {{ '{nama}' }} belum melakukan daftar ulang.">{{ old('message', $message) }}</textarea>
            @error('message')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            <p class="mt-2 text-sm text-slate-500">
                Gunakan <code>{nama}</code> untuk nama murid dan <code>{nomor_pendaftaran}</code> untuk nomor pendaftaran.
                Biarkan kosong untuk membuka WhatsApp tanpa pesan otomatis.
            </p>
        </div>

        <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Simpan Template</button>
    </form>
@endsection
