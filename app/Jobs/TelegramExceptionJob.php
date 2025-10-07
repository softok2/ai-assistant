<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use App\Dtos\TelegramExceptionDto;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TelegramExceptionNotification;

final class TelegramExceptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly TelegramExceptionDto $exceptionDto) {}

    public function handle(): void
    {
        try {
            Notification::route('telegram', config('services.telegram-bot-api.chat_id'))
                ->notify(new TelegramExceptionNotification(
                    $this->exceptionDto,
                ));
        } catch (Throwable $exception) {
            Log::error('Failed to send Telegram exception notification: '.$exception->getMessage(), $exception->getTrace());
        }
    }
}
