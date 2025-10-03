<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

final class Media extends Model
{
    public static function addFromPentaho(string $fileName, $content): self
    {
        Storage::delete('docs/'.$fileName);
        Storage::put('docs/'.$fileName, $content);

        return self::updateOrCreate([
            'name' => $fileName,
        ], [
            'path' => 'docs/'.$fileName,
        ]);
    }
}
