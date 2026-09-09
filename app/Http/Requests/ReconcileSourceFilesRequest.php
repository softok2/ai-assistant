<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Varios entornos comparten cuenta y vector store de OpenAI. Por defecto la
 * reconciliación solo toca lo del entorno actual; `include_untagged` suma lo
 * que no se puede atribuir a ninguno.
 */
final class ReconcileSourceFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->isAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'include_untagged' => ['sometimes', 'boolean'],
        ];
    }

    public function includeUntagged(): bool
    {
        return $this->boolean('include_untagged');
    }
}
