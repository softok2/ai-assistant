<?php

declare(strict_types=1);

// config for Softok2/TelegramNotification
return [
    'bot_token' => env('SOFTOK2_TELEGRAM_BOT_TOKEN', null),
    'chat_id' => env('SOFTOK2_TELEGRAM_CHAT_ID', null),
];
