<?php

declare(strict_types=1);

namespace App\Notifications;

use Throwable;
use JsonException;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Storage\EntryModel;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\Telegram\TelegramMessage;

final class TelegramExceptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Throwable $exception) {}

    public function via($notifiable): array
    {
        return ['telegram'];
    }

    /**
     * @throws JsonException
     */
    public function toTelegram(): TelegramMessage
    {
        $exception = $this->exception;

        $content = sprintf(
            "🚨 *%s Exception Alert*\n\n"."*Message:* `%s`\n".
            "*File:* `%s`\n"."*Line:* `%d`\n"."*Date:* %s\n\n",
            config('app.name'),
            str_replace('`', '', $exception->getMessage()),
            $exception->getFile(),
            $exception->getLine(),
            now()->toDateTimeString(),
        );

        $uuid = $this->log($content, $exception->getTrace());

        $button = app()->isLocal() ? 'button' : 'buttonWithWebApp';

        return TelegramMessage::create()
            ->content($content)
            ->options(['parse_mode' => 'Markdown'])
            ->{$button}('View stack trace', url('/telescope/logs/'.$uuid));
    }

    private function log($content, array $trace = []): ?string
    {
        Log::error($content, $trace);

        return EntryModel::latest('created_at')->value('uuid');
    }
}
