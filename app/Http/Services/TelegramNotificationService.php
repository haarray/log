<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;

class TelegramNotificationService
{
    public function sendMessage(string $chatId, string $message): bool
    {
        $token = config('haarray.telegram.token');
        if (!$token || trim($chatId) === '' || trim($message) === '') {
            return false;
        }

        $response = Http::asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        return $response->successful() && ($response->json('ok') === true);
    }
}

