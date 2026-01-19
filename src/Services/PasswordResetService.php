<?php

namespace App\Services;

use App\Models\PasswordReset;
use App\Models\User;
use Carbon\Carbon;

class PasswordResetService
{
    public function __construct(
        private int $ttlMinutes = 60
    ) {}

    public function createForUser(User $user): string
    {
        PasswordReset::where('user_id', $user->id)->delete();

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        PasswordReset::create([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => Carbon::now()->addMinutes($this->ttlMinutes),
            'used_at' => null,
        ]);

        return $token;
    }

    public function getValidReset(string $token): ?PasswordReset
    {
        if ($token === '') {
            return null;
        }

        $tokenHash = hash('sha256', $token);

        return PasswordReset::where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    public function markUsed(PasswordReset $reset): void
    {
        $reset->used_at = Carbon::now();
        $reset->save();
    }

    public function invalidateUser(User $user): void
    {
        PasswordReset::where('user_id', $user->id)->delete();
    }
}
