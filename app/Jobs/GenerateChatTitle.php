<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\ChatTitleGenerator;
use App\Models\Chat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

final class GenerateChatTitle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Chat $chat)
    {
    }

    public function handle(): void
    {
        $exchange = $this->chat->messages()
            ->orderBy('created_at')
            ->limit(2)
            ->get()
            ->map(fn ($message) => ($message->role === 'user' ? 'Usuario: ' : 'Asistente: ').($message->parts['text'] ?? ''))
            ->join("\n");

        try {
            $title = trim((string) (new ChatTitleGenerator)->prompt($exchange), " \n\"'.");
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($title !== '') {
            $this->chat->update(['title' => Str::limit($title, 80)]);
        }
    }
}
