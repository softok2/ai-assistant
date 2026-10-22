<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\Chat;
use App\Ai\ClubAiProvider;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Ai\Agents\ChatTitleGenerator;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class GenerateChatTitle implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Chat $chat) {}

    public function handle(ClubAiProvider $providers): void
    {
        $exchange = $this->chat->messages()->oldest()
            ->limit(2)
            ->get()
            ->map(fn ($message): string => ($message->role === 'user' ? 'Usuario: ' : 'Asistente: ').($message->parts['text'] ?? ''))
            ->join("\n");

        try {
            $title = mb_trim(
                (string) (new ChatTitleGenerator)->prompt($exchange, provider: $providers->nameFor($this->chat->user?->clubName())),
                " \n\"'."
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return;
        }

        if ($title !== '') {
            $this->chat->update(['title' => Str::limit($title, 80)]);
        }
    }
}
