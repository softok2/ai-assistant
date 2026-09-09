<?php

declare(strict_types=1);

namespace App\Actions\Chats;

use App\Models\File;
use Illuminate\Support\Collection;
use App\Dtos\AssistantStreamInsights;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Streaming\Events\ToolResult;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;

/**
 * Lee los eventos de un stream del SDK y saca de ahí qué herramientas usó el
 * asistente y qué fuentes citó. El SDK no expone esto ya resuelto: hay que
 * recorrer los eventos crudos.
 */
final class ResolveStreamInsightsAction
{
    private const FILE_SEARCH = 'file_search_call';

    private const WEB_SEARCH = 'web_search_call';

    /**
     * @param  Collection<int, object>  $events
     */
    public function execute(Collection $events): AssistantStreamInsights
    {
        /** @var array<string, array{type: string, label: string, status: string}> $activity */
        $activity = [];

        /** @var array<int, array{file_id: ?string, filename: ?string}> $documentHits */
        $documentHits = [];

        /** @var array<string, array<string, mixed>> $webSources */
        $webSources = [];

        $usedFileSearch = false;

        foreach ($events as $event) {
            match (true) {
                $event instanceof ProviderToolEvent => $this->readProviderTool(
                    $event, $activity, $documentHits, $usedFileSearch
                ),
                $event instanceof ToolCall => $activity['tool:'.$event->toolCall->id] = [
                    'type' => 'tool',
                    'label' => $event->toolCall->name,
                    'status' => 'in_progress',
                ],
                $event instanceof ToolResult => $activity['tool:'.$event->toolResult->id] = [
                    'type' => 'tool',
                    'label' => $event->toolResult->name,
                    'status' => $event->successful ? 'completed' : 'failed',
                ],
                $event instanceof Citation => $this->readCitation($event, $webSources),
                default => null,
            };
        }

        return new AssistantStreamInsights(
            array_values($activity),
            [...$this->documentSources($documentHits, $usedFileSearch), ...array_values($webSources)],
        );
    }

    /**
     * @param  array<string, array{type: string, label: string, status: string}>  $activity
     * @param  array<int, array{file_id: ?string, filename: ?string}>  $documentHits
     */
    private function readProviderTool(
        ProviderToolEvent $event,
        array &$activity,
        array &$documentHits,
        bool &$usedFileSearch,
    ): void {
        $type = match ($event->type) {
            self::FILE_SEARCH => 'file_search',
            self::WEB_SEARCH => 'web_search',
            default => 'tool',
        };

        $activity[$type.':'.$event->itemId] = [
            'type' => $type,
            'label' => $this->label($type, $event->type),
            'status' => $this->status($event->status),
        ];

        if ($type !== 'file_search') {
            return;
        }

        $usedFileSearch = true;

        foreach ($this->resultsOf($event->data) as $result) {
            $documentHits[] = [
                'file_id' => is_string($result['file_id'] ?? null) ? $result['file_id'] : null,
                'filename' => is_string($result['filename'] ?? null) ? $result['filename'] : null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function resultsOf(array $data): array
    {
        $results = $data['results'] ?? $data['item']['results'] ?? [];

        if (! is_array($results)) {
            return [];
        }

        return array_values(array_filter($results, 'is_array'));
    }

    /**
     * @param  array<string, array<string, mixed>>  $webSources
     */
    private function readCitation(Citation $event, array &$webSources): void
    {
        if (! $event->citation instanceof UrlCitation) {
            return;
        }

        $webSources[$event->citation->url] = [
            'kind' => 'web',
            'title' => $event->citation->title ?? $event->citation->url,
            'url' => $event->citation->url,
            'document_name' => null,
            'synced_at' => null,
        ];
    }

    /**
     * Cruza los archivos citados con la biblioteca. Cuando el proveedor no
     * dice qué documento usó, queda una sola fuente genérica.
     *
     * @param  array<int, array{file_id: ?string, filename: ?string}>  $hits
     * @return array<int, array<string, mixed>>
     */
    private function documentSources(array $hits, bool $usedFileSearch): array
    {
        if (! $usedFileSearch) {
            return [];
        }

        $mediaIds = array_values(array_filter(array_column($hits, 'file_id')));
        $names = array_values(array_filter(array_column($hits, 'filename')));

        if ($mediaIds === [] && $names === []) {
            return [$this->genericLibrarySource()];
        }

        $files = File::query()
            ->when($mediaIds !== [], fn ($query) => $query->orWhereIn('assistant_media_id', $mediaIds))
            ->when($names !== [], fn ($query) => $query->orWhereIn('name', $names))
            ->get();

        $sources = $files
            ->map(fn (File $file): array => [
                'kind' => 'document',
                'title' => $this->documentTitle($file->name),
                'url' => null,
                'document_name' => $file->name,
                'synced_at' => $file->synced_at?->toISOString(),
            ])
            ->values()
            ->all();

        foreach ($names as $name) {
            if ($files->doesntContain('name', $name)) {
                $sources[] = [
                    'kind' => 'document',
                    'title' => $this->documentTitle($name),
                    'url' => null,
                    'document_name' => $name,
                    'synced_at' => null,
                ];
            }
        }

        return $sources === [] ? [$this->genericLibrarySource()] : $sources;
    }

    /**
     * @return array<string, mixed>
     */
    private function genericLibrarySource(): array
    {
        return [
            'kind' => 'document',
            'title' => 'Biblioteca del club',
            'url' => null,
            'document_name' => null,
            'synced_at' => null,
        ];
    }

    private function documentTitle(string $name): string
    {
        return pathinfo($name, PATHINFO_FILENAME) ?: $name;
    }

    private function label(string $type, string $providerType): string
    {
        return match ($type) {
            'file_search' => 'Consulté la biblioteca del club',
            'web_search' => 'Busqué en la web',
            default => $providerType,
        };
    }

    private function status(string $status): string
    {
        return match ($status) {
            'completed', 'done' => 'completed',
            'failed', 'incomplete', 'error' => 'failed',
            default => 'in_progress',
        };
    }
}
