<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

it('has the laravel/ai 1.x conversation schema after a fresh migrate', function (): void {
    expect(Schema::hasColumns('agent_conversations', ['participant_type', 'participant_id', 'title']))->toBeTrue()
        ->and(Schema::hasColumn('agent_conversations', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumns('agent_conversation_messages', ['participant_type', 'participant_id', 'steps', 'status']))->toBeTrue()
        ->and(Schema::hasColumn('agent_conversation_messages', 'tool_calls'))->toBeFalse();
});

it('upgrades tables that still carry the 0.7 schema', function (): void {
    Schema::dropIfExists('agent_conversation_messages');
    Schema::dropIfExists('agent_conversations');
    Schema::create('agent_conversations', function (Blueprint $table): void {
        $table->string('id', 36)->primary();
        $table->foreignId('user_id')->nullable();
        $table->string('title');
        $table->timestamps();
    });
    Schema::create('agent_conversation_messages', function (Blueprint $table): void {
        $table->string('id', 36)->primary();
        $table->string('conversation_id', 36)->index();
        $table->foreignId('user_id')->nullable();
        $table->text('tool_calls');
        $table->text('tool_results');
        $table->timestamps();
    });

    $migration = require database_path('migrations/2026_10_22_000000_recreate_agent_conversations_for_laravel_ai_1.php');
    $migration->up();

    expect(Schema::hasColumn('agent_conversation_messages', 'steps'))->toBeTrue()
        ->and(Schema::hasColumn('agent_conversation_messages', 'tool_calls'))->toBeFalse();
});
