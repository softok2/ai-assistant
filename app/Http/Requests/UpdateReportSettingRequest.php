<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReportFrequency;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateReportSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'recipients' => ['array', 'max:10'],
            'recipients.*' => ['email'],
            'frequency' => ['required', Rule::enum(ReportFrequency::class)],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:31'],
            'hour' => ['required', 'integer', 'min:0', 'max:23'],
        ];
    }
}
