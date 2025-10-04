<?php

declare(strict_types=1);

namespace App\Models;

use Log;
use Exception;
use Throwable;
use App\Enums\MediaStatus;
use App\AI\OpenAIAssistant;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;

final class Media extends Model
{
    protected $guarded = ['id'];

    public static function fromPentaho(string $fileName, $content): self
    {
        Storage::put('docs/'.$fileName, $content);

        return self::create([
            'name' => $fileName,
        ]);
    }

    public static function markAsExpired(self $media): void
    {
        self::query()
            ->whereKeyNot($media->id)
            ->where('status', MediaStatus::IN_PROGRESS->value)
            ->where('group', $media->group)
            ->whereNull('expired_at')
            ->update(['expired_at' => now()]);
    }

    public function upload(): self
    {
        $assistant = new OpenAIAssistant(
            assistantId: config('services.openai.assistant_id'),
            vectorStoreId: config('services.openai.vector_store_id'),
        );

        $filePath = Storage::path('docs/'.$this->name);

        try {
            $fileCreateResponse = $assistant->feed($filePath);

            $this->assistant_media_id = $fileCreateResponse->id;
            $this->status = MediaStatus::IN_PROGRESS;
            $this->bytes = $fileCreateResponse->bytes;
            $this->synced_at = now();
        } catch (Exception $e) {
            Log::error('Media upload failed: '.$e->getMessage());
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
        $assistant = new OpenAIAssistant(
            assistantId: config('services.openai.assistant_id'),
            vectorStoreId: config('services.openai.vector_store_id'),
        );

        DB::beginTransaction();

        try {
            $assistant->deleteFile($this->assistant_media_id);
            Storage::delete('docs/'.$this->name);
            $this->delete();

            DB::commit();
        } catch (Exception $e) {
            dd($e->getMessage());
            DB::rollBack();
            Log::error('Media deletion from OpenAI failed: '.$e->getMessage());
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
