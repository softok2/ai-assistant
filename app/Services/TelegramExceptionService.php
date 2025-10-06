<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;
use Illuminate\Support\Facades\Log;
use App\Exceptions\TelegramException;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TelegramExceptionNotification;

final readonly class TelegramExceptionService
{
    public function __construct(private Throwable $exception) {}

    public static function make(Throwable $exception): self
    {
        return new self($exception);
    }

    /**
     * @throws TelegramException
     */
    public function send(): self
    {
        try {
            Notification::route('telegram', config('services.telegram-bot-api.chat_id'))
                ->notify(new TelegramExceptionNotification(
                    $this->exception
                ));
        } catch (Throwable $exception) {
            Log::error('Failed to send Telegram exception notification: '.$exception->getMessage(), $exception->getTrace());

            throw new TelegramException();
        }

        return $this;
    }
}
