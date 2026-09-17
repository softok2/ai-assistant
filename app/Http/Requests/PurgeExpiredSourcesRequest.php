<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClubName;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Un admin externo nunca manda `club` (va acotado al suyo); uno local sí, y
 * cuando lo hace tiene que ser uno de los clubes conocidos: por eso `club` es
 * opcional pero, si viene, se valida igual que en reconciliar y subir
 * documentos. Sin `club`, `ResolveSourcesClubAction` decide el club por
 * defecto.
 */
final class PurgeExpiredSourcesRequest extends FormRequest
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
            'club' => ['sometimes', 'string', Rule::in(array_column(ClubName::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'club.in' => 'Ese club no está configurado.',
        ];
    }

    public function club(): ?string
    {
        return $this->string('club')->value() ?: null;
    }
}
