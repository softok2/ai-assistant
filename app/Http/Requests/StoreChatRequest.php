<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ModelName;
use App\Enums\Visibility;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

final class StoreChatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:4000',
            'model' => ['nullable', 'string', Rule::enum(ModelName::class)],
            'visibility' => ['required', 'string', Rule::enum(Visibility::class)],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*.path' => ['required', 'string'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
            'attachments.*.mime' => ['required', 'string', 'max:127'],
        ];
    }
}
