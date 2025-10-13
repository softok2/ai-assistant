<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Enums\ClubName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Dtos\ExternalLinkPayloadDto;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

final class AuthenticateExternalUser
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $request->validate([
                'club' => ['required', new Enum(ClubName::class)],
                'user_name' => 'required|string|max:255',
                'role_id' => 'required|integer|exists:roles,id',
                'token' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            Log::error($e->getMessage());

            abort(400, 'Bad Request: '.$e->getMessage());
        }

        // Check if token was created using the signature...
        if (! $this->isValidExternalLink(
            club: ClubName::from($request->club),
            userName: $request->user_name,
            token: $request->token
        )) {
            abort(403, 'Forbidden: Invalid token');
        }

        $user = User::fromExternalLink(
            payload: ExternalLinkPayloadDto::fromRequest($request->all())
        );

       auth()->login($user);

       return $next($request);
    }

    private function isValidExternalLink(ClubName $club, string $userName, string $token): bool
    {
        $computedSignature = hash_hmac('sha256', "$club->value|$userName", config('app.club_signature_secrets.'.$club->value, ''));

        return hash_equals($computedSignature, $token);
    }
}
