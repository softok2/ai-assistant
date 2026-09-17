<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ClubName;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSourceFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:md,txt,pdf', 'max:10240'],
            'group' => ['required', 'string', 'alpha_dash', 'lowercase', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'club.required' => 'Indica el club del documento.',
            'club.in' => 'Ese club no está configurado.',
            'file.required' => 'Elige un documento.',
            'file.mimes' => 'El documento debe ser .md, .txt o .pdf.',
            'file.max' => 'El documento no puede pasar de 10 MB.',
            'group.required' => 'Indica el grupo del documento.',
            'group.alpha_dash' => 'El grupo solo admite letras, números y guiones.',
            'group.lowercase' => 'El grupo debe ir en minúsculas.',
            'group.max' => 'El grupo no puede pasar de 40 caracteres.',
        ];
    }
}
