<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClubName;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Varios entornos comparten cuenta y vector store de OpenAI. Por defecto la
 * reconciliación solo toca lo del entorno actual; `include_untagged` suma lo
 * que no se puede atribuir a ninguno. Cada club tiene su propio store.
 */
final class ReconcileSourceFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->isAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'club' => ['required', Rule::in(array_column(ClubName::cases(), 'value'))],
            'include_untagged' => ['sometimes', 'boolean'],
        ];
    }

    public function club(): ClubName
    {
        return ClubName::from($this->string('club')->value());
    }

    public function includeUntagged(): bool
    {
        return $this->boolean('include_untagged');
    }
}
