<?php

namespace App\Policies;

use App\Models\attendance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AttendanceRecordPolicy
{
    public function before(User $user, $ability)
    {
        if ($user->admin_status) {
            return true;
        }

        // 各操作の認可は定義によるためfalseではなくnullを返す
        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, attendance $attendance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, attendance $attendance): bool
    {
        // 本人または管理者のみ有効
        if (($user->id === $attendance->user_id) || $user->admin_status) {
            return true;
        }

        // App\Exceptions\Handler.phpでエラー時のjsonを定義
        throw new AuthorizationException;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, attendance $attendance): bool
    {
        // 本人または管理者のみ有効
        if (($user->id === $attendance->user_id) || $user->admin_status) {
            return true;
        }

        // App\Exceptions\Handler.phpでエラー時のjsonを定義
        throw new AuthorizationException;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, attendance $attendance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, attendance $attendance): bool
    {
        return true;
    }
}
