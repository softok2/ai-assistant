<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class VectorStore extends Model
{
    use HasFactory;

    protected $fillable = ['club', 'environment', 'provider', 'store_id', 'name'];
}
