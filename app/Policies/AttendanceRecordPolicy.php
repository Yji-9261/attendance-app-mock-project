<?php

namespace App\Policies;

use App\Models\User;
use App\Models\attendance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;

class AttendanceRecordPolicy
{
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
    public function view(User $user, Attendance $attendance): bool
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
    public function update(User $user, Attendance $attendance): bool
    {
        // 本人または管理者のみ有効
        if (($user->id === $attendance->user_id) || $user->admin_status) {
            return true;
        }

        // App\Exceptions\Handler.phpでエラー時のjsonを定義
        throw new AuthorizationException();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        // 本人または管理者のみ有効
        if (($user->id === $attendance->user_id) || $user->admin_status) {
            return true;
        }

        // App\Exceptions\Handler.phpでエラー時のjsonを定義
        throw new AuthorizationException();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attendance $attendance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return true;
    }
}
