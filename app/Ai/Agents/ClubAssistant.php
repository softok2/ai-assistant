<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Message as ChatMessage;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Providers\Tools\WebSearch;

#[Provider(Lab::OpenAI)]
final class ClubAssistant implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  Collection<int, ChatMessage>|null  $history  prior chat messages, oldest first
     */
    public function __construct(protected ?Collection $history = null)
    {
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return file_get_contents(resource_path('prompts/club_assistant.md'));
    }

    /**
     * Get the model the agent should use.
     */
    public function model(): string
    {
        return config('services.openai.model');
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        return ($this->history ?? new Collection)
            ->map(fn (ChatMessage $message): Message => new Message(
                $message->role,
                $message->parts['text'] ?? ''
            ))
            ->all();
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new FileSearch(stores: [config('services.openai.vector_store_id')]),
            new WebSearch,
        ];
    }
}
