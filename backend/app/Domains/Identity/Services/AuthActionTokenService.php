<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\AuthActionToken;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthActionTokenService
{
    public function issue(User $user, string $purpose, ?string $tenantId = null, int $ttlMinutes = 60, ?array $meta = null): string
    {
        $plain = Str::random(64);

        AuthActionToken::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'meta' => $meta,
        ]);

        return $plain;
    }

    public function consume(string $plainToken, string $purpose): AuthActionToken
    {
        $token = AuthActionToken::query()
            ->where('purpose', $purpose)
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || ! $token->isValid()) {
            throw ValidationException::withMessages([
                'token' => ['This link is invalid or has expired.'],
            ]);
        }

        $token->forceFill(['consumed_at' => now()])->save();

        return $token;
    }

    public function peek(string $plainToken, string $purpose): AuthActionToken
    {
        $token = AuthActionToken::query()
            ->where('purpose', $purpose)
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || ! $token->isValid()) {
            throw ValidationException::withMessages([
                'token' => ['This link is invalid or has expired.'],
            ]);
        }

        return $token;
    }
}
