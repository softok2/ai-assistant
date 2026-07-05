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
    /**
     * Maximum age of a signed link, in seconds. The link only authenticates
     * the entry; the session keeps the user signed in afterwards.
     */
    private const MAX_LINK_AGE = 300;

    private const MAX_CLOCK_SKEW = 60;

    public function handle(Request $request, Closure $next)
    {
        try {
            $request->validate([
                'club' => ['required', new Enum(ClubName::class)],
                'user_id' => 'required|integer|min:1',
                'user_name' => 'required|string|max:255',
                'role' => 'required|string|exists:roles,name',
                'issued_at' => 'required|integer',
                'sig' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            Log::warning('External link rejected: '.$e->getMessage());

            abort(400, 'Bad Request: '.$e->getMessage());
        }

        abort_unless($this->isFresh((int) $request->issued_at), 403, 'Forbidden: Link expired');

        abort_unless($this->hasValidSignature($request), 403, 'Forbidden: Invalid signature');

        $user = User::fromExternalLink(
            payload: ExternalLinkPayloadDto::fromRequest($request->all())
        );

        auth()->login($user);

        $request->session()->regenerate();

        return $next($request);
    }

    private function isFresh(int $issuedAt): bool
    {
        $age = now()->getTimestamp() - $issuedAt;

        return $age <= self::MAX_LINK_AGE && $age >= -self::MAX_CLOCK_SKEW;
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('app.club_signature_secrets.'.$request->club);

        if (blank($secret)) {
            Log::error("No signature secret configured for club [{$request->club}].");

            return false;
        }

        $payload = implode('|', [
            $request->club,
            $request->user_id,
            $request->user_name,
            $request->role,
            $request->issued_at,
        ]);

        return hash_equals(hash_hmac('sha256', $payload, $secret), (string) $request->sig);
    }
}
