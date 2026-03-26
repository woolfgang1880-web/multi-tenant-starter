<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Models\UserSession;

/**
 * Revoca tokens y sesiones de un usuario (logout global, inactivación, etc.).
 */
final class UserAccessRevoker
{
    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();

        UserSession::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'invalidated_at' => now(),
                'invalidation_reason' => 'access_revoked',
            ]);

        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
