<?php

declare(strict_types=1);

namespace App\Models;

use Throwable;
use Laravel\Ai\Stores;
use App\Enums\MediaStatus;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class File extends Model
{
    use HasFactory;

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

    /**
     * Reclama un pendiente para subirlo. Es un UPDATE condicional: si otro
     * worker ya lo tomó (o caducó mientras esperaba) devuelve null y el que
     * llama no sube nada. Antes el estado "en proceso" vivía solo en memoria y
     * dos workers subían el mismo archivo a OpenAI.
     */
    public static function claimForUpload(int $id): ?self
    {
        $claimed = self::query()
            ->whereKey($id)
            ->where('status', MediaStatus::PENDING->value)
            ->whereNull('expired_at')
            ->update(['status' => MediaStatus::IN_PROGRESS->value]);

        return $claimed === 1 ? self::find($id) : null;
    }

    /**
     * Caduca los documentos MÁS VIEJOS del grupo (cualquier estado) una vez que
     * este archivo quedó indexado. Solo los más viejos: si dos pendientes del
     * mismo grupo se suben a la vez, se caducarían mutuamente y el grupo se
     * quedaría sin documento.
     */
    public function expireSiblings(): void
    {
        self::query()
            ->where('id', '<', $this->id)
            ->where('group', $this->group)
            ->whereNull('expired_at')
            ->update(['expired_at' => now()]);
    }

    public function upload(): self
    {
        try {
            $store = Stores::get(config('services.openai.vector_store_id'));

            $document = $store->add(
                Document::fromStorage('docs/'.$this->name),
                metadata: ['group' => $this->group],
            );

            $this->assistant_media_id = $document->fileId ?? $document->id;
            $this->status = MediaStatus::COMPLETED;
            $this->bytes = Storage::size('docs/'.$this->name);
            $this->synced_at = now();
            $this->save();

            $this->expireSiblings();
        } catch (Throwable $e) {
            Log::error("Media upload to OpenAI failed for {$this->name}: ".$e->getMessage());
            $this->status = MediaStatus::FAILED;
            $this->save();
        }

        return $this;
    }

    /**
     * Borra el documento del store y de la cuenta de OpenAI, del disco y de la
     * base. Un 404 de OpenAI cuenta como borrado (ya no estaba); cualquier otro
     * error conserva la fila para reintentar en la siguiente corrida.
     */
    public function remove(): self
    {
        try {
            if ($this->assistant_media_id) {
                $this->removeFromProvider();
            }

            Storage::delete('docs/'.$this->name);
            $this->delete();
        } catch (Throwable $e) {
            Log::error("Media removal failed for {$this->name}: ".$e->getMessage());
        }

        return $this;
    }

    /**
     * Deja la fila lista para volver a indexarse: quita el documento de OpenAI
     * (un 404 cuenta como quitado) y la devuelve al estado de recién importada.
     */
    public function resetForReindex(): self
    {
        if ($this->assistant_media_id) {
            $this->removeFromProvider();
        }

        $this->assistant_media_id = null;
        $this->status = MediaStatus::PENDING;
        $this->synced_at = null;
        $this->expired_at = null;
        $this->save();

        return $this;
    }

    /**
     * Si el documento sigue en disco. Sin él no hay nada que volver a subir.
     */
    public function hasStoredContent(): bool
    {
        return Storage::exists('docs/'.$this->name);
    }

    protected function removeFromProvider(): void
    {
        try {
            Stores::get(config('services.openai.vector_store_id'))
                ->remove($this->assistant_media_id, deleteFile: true);
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                throw $e;
            }
        }
    }

    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'synced_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::PENDING->value)->whereNull('expired_at');
    }

    #[Scope]
    protected function expired(Builder $query): Builder
    {
        return $query->whereNotNull('expired_at');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('expired_at');
    }
}
