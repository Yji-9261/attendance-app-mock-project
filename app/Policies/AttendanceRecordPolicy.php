<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * Perform pre-authorization checks
     *
     * @param  User  $user ユーザーモデル
     * @param  string  $ability アクション
     * @return bool|null 管理者ならtrue、それ以外はnull
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
     * @param  User  $user ユーザーモデル
     * @return bool 常にtrue
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user  ユーザーモデル
     * @param  Attendance  $attendance  勤怠モデル
     * @return bool 本人の勤怠ならtrue、それ以外false
     */
    public function view(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    /**
     * Determine whether the user can create models.
     * 
     * @param  User  $user  ユーザーモデル
     * @return bool 常にtrue
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user  ユーザーモデル
     * @param  Attendance  $attendance  勤怠モデル
     * @return bool 本人の勤怠ならtrue、それ以外false
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * @param  User  $user  ユーザーモデル
     * @param  Attendance  $attendance  勤怠モデル
     * @return bool 本人の勤怠ならtrue、それ以外false
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     * 
     * @param  User  $user  ユーザーモデル
     * @param  Attendance  $attendance  勤怠モデル
     * @return bool 常にtrue
     */
    public function restore(User $user, Attendance $attendance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * 
     * @param  User  $user  ユーザーモデル
     * @param  Attendance  $attendance  勤怠モデル
     * @return bool 常にtrue
     */
    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return true;
    }
}
