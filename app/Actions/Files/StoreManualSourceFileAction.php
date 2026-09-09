<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Models\File;
use App\Enums\MediaStatus;
use App\Jobs\UploadAssistantDoc;
use Illuminate\Http\UploadedFile;
use App\Dtos\ManualSourceFileData;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda un documento subido a mano con el mismo patrón de nombre que el
 * import de Pentaho (`<grupo>-…`), para que el resto del pipeline (caducar
 * hermanos del grupo, reconciliar contra OpenAI) lo trate igual.
 */
final class StoreManualSourceFileAction
{
    public function execute(ManualSourceFileData $data): File
    {
        $name = sprintf(
            '%s-manual-%d-%s.%s',
            $data->group,
            now()->timestamp,
            bin2hex(random_bytes(3)),
            $this->extensionFor($data->file),
        );

        Storage::putFileAs('docs', $data->file, $name);

        $file = File::query()->create([
            'project' => 'manual',
            'group' => $data->group,
            'name' => $name,
            'status' => MediaStatus::PENDING,
            'bytes' => Storage::size('docs/'.$name),
        ]);

        UploadAssistantDoc::dispatch($file->id);

        return $file;
    }

    /**
     * La extensión sale del tipo real del archivo, no del nombre que mandó el
     * navegador: si no, un `notas.php` de texto plano se guardaba en disco con
     * extensión .php. Markdown se detecta a menudo como texto plano, así que
     * ahí sí respetamos la extensión original.
     */
    private function extensionFor(UploadedFile $file): string
    {
        $guessed = mb_strtolower((string) $file->guessExtension());

        if ($guessed === 'pdf') {
            return 'pdf';
        }

        $original = mb_strtolower($file->getClientOriginalExtension());

        if ($original === 'md' && in_array($guessed, ['md', 'markdown', 'txt'], true)) {
            return 'md';
        }

        return $guessed === 'md' || $guessed === 'markdown' ? 'md' : 'txt';
    }
}
