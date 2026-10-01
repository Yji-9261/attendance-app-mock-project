<?php

namespace App\Policies;

use App\Models\attendance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AttendancePolicy
{
    /**
     * Perform pre-authorization checks
     *
     * @param  User  $user  ユーザーモデル
     * @param  string  $ability  アクション
     * @return bool|null
     */
    public function before(User $user, string $ability)
    {
        if ($user->admin_status) {
            return true;
        }
        return null;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  User  $user  ユーザーモデル
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user  ユーザーモデル
     * @param  attendance  $attendance  勤怠モデル
     */
    public function view(User $user, attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  $user  ユーザーモデル
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user  ユーザーモデル
     * @param  attendance  $attendance  勤怠モデル
     *
     * @throws AuthorizationException
     */
    public function update(User $user, attendance $attendance): bool
    {
        if ($user->id === $attendance->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user  ユーザーモデル
     * @param  attendance  $attendance  勤怠モデル
     *
     * @throws AuthorizationException
     */
    public function delete(User $user, attendance $attendance): bool
    {
        if ($user->id === $attendance->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user  ユーザーモデル
     * @param  attendance  $attendance  勤怠モデル
     */
    public function restore(User $user, attendance $attendance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user  ユーザーモデル
     * @param  attendance  $attendance  勤怠モデル
     */
    public function forceDelete(User $user, attendance $attendance): bool
    {
        return true;
    }
}
