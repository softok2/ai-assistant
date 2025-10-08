<?php

declare(strict_types=1);

namespace App\Models;

use Throwable;
use App\Enums\MediaStatus;
use App\AI\OpenAIAssistant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;

final class File extends Model
{
    protected $guarded = ['id'];

    public static function fromPentaho(string $fileName, $content): self
    {
        $file = self::firstOrCreate([
            'name' => $fileName,
            'group' => Str::of($fileName)->basename('.md')->before('-'),
        ]);

        if ($file->wasRecentlyCreated) {
            Storage::put('docs/'.$fileName, $content);
        }

        return $file;
    }

    public static function markAsExpired(self $media): void
    {
        self::query()
            ->whereKeyNot($media->id)
            ->where('status', MediaStatus::COMPLETED->value)
            ->where('group', $media->group)
            ->whereNull('expired_at')
            ->update(['expired_at' => now()]);
    }

    public function upload(): self
    {
        try {
            $assistant = new OpenAIAssistant(
                assistantId: config('services.openai.assistant_id'),
                vectorStoreId: config('services.openai.vector_store_id'),
            );

            $filePath = Storage::path('docs/'.$this->name);

            $this->status = MediaStatus::IN_PROGRESS;
            $fileCreateResponse = $assistant->feed($filePath);

            $this->assistant_media_id = $fileCreateResponse->id;
            $this->status = MediaStatus::COMPLETED;
            $this->bytes = $fileCreateResponse->bytes;
            $this->synced_at = now();
        } catch (Throwable $e) {
            Log::error("Media upload to OpenAI failed for {$this->name}: ".$e->getMessage(), $e->getTrace() ?? []);
            $this->status = MediaStatus::FAILED;
        } finally {
            $this->save();
        }

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function remove(): self
    {

        DB::beginTransaction();

        try {
            $assistant = new OpenAIAssistant(
                assistantId: config('services.openai.assistant_id'),
                vectorStoreId: config('services.openai.vector_store_id'),
            );
            $assistant->deleteFile($this->assistant_media_id);

            Storage::delete('docs/'.$this->name);
            $this->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("Media removal failed for {$this->name}: ".$e->getMessage(), $e->getTrace() ?? []);
        }

        return $this;
    }

    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
        ];
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::PENDING->value);
    }

    #[Scope]
    protected function expired(Builder $query): Builder
    {
        return $query->whereNotNull('expired_at');
    }
}
