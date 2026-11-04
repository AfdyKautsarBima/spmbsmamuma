<?php

namespace App\Services;

use App\Models\Applicant;
use Illuminate\Support\Facades\DB;

class WhatsAppMessageTemplate
{
    public function urlFor(Applicant $applicant, string $phone): string
    {
        $template = (string) (DB::table('app_settings')->where('key', 'whatsapp_message_template')->value('value') ?? '');
        if ($template === '') {
            return "https://wa.me/{$phone}";
        }

        $message = strtr($template, [
            '{nama}' => $applicant->name,
            '{nomor_pendaftaran}' => $applicant->registration_number,
        ]);

        return "https://wa.me/{$phone}?text=".rawurlencode($message);
    }
}
