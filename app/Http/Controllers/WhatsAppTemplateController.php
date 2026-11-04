<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WhatsAppTemplateController extends Controller
{
    private const SETTING_KEY = 'whatsapp_message_template';

    public function edit(): View
    {
        return view('whatsapp-template.edit', [
            'message' => DB::table('app_settings')->where('key', self::SETTING_KEY)->value('value') ?? '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $message = isset($validated['message']) ? trim($validated['message']) : null;
        $setting = DB::table('app_settings')->where('key', self::SETTING_KEY)->first();
        $oldMessage = $setting?->value;

        if ($setting) {
            DB::table('app_settings')->where('key', self::SETTING_KEY)->update([
                'value' => $message,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('app_settings')->insert([
                'key' => self::SETTING_KEY,
                'value' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($oldMessage !== $message) {
            ActivityLog::record(
                'whatsapp_template.updated',
                'Template pesan WhatsApp diperbarui',
                null,
                ['message' => $oldMessage],
                ['message' => $message],
            );
        }

        return redirect()->route('whatsapp-template.edit')->with('success', 'Template pesan WhatsApp disimpan.');
    }
}
