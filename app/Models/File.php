<?php

declare(strict_types=1);

namespace App\Models;

use Throwable;
use App\Enums\MediaStatus;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Stores;
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
            $store = Stores::get(config('services.openai.vector_store_id'));

            $this->status = MediaStatus::IN_PROGRESS;

            $document = $store->add(
                Document::fromStorage('docs/'.$this->name),
                metadata: ['group' => $this->group],
            );

            $this->assistant_media_id = $document->fileId ?? $document->id;
            $this->status = MediaStatus::COMPLETED;
            $this->bytes = Storage::size('docs/'.$this->name);
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
            Stores::get(config('services.openai.vector_store_id'))
                ->remove($this->assistant_media_id, deleteFile: true);

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
