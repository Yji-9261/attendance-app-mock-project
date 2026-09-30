<?php

namespace App\Policies;

use App\Models\attendance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AttendanceRecordPolicy
{
    /**
     * Perform pre-authorization checks
     * 
     * @param User $user ユーザーモデル
     * @param string $ability アクション
     * @return bool|null
     */
    public function before(User $user, string $ability)
    {
        if ($user->admin_status) {
            return true;
        }

        // 一般ユーザーの各操作の認可は定義によるためfalseではなくnullを返す
        return null;
    }

    /**
     * Determine whether the user can view any models.
     * 
     * @param User $user ユーザーモデル
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * 
     * @param User $user ユーザーモデル
     * @param attendance $attendance 勤怠モデル
     * @return bool
     */
    public function view(User $user, attendance $attendance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * 
     * @param User $user ユーザーモデル
     * @return bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * 
     * @param User $user ユーザーモデル
     * @param attendance $attendance 勤怠モデル
     * @throws AuthorizationException
     * @return bool
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
     * 
     * @param User $user ユーザーモデル
     * @param attendance $attendance 勤怠モデル
     * @throws AuthorizationException
     * @return bool
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
     * 
     * @param User $user ユーザーモデル
     * @param attendance $attendance 勤怠モデル
     * @return bool
     */
    public function restore(User $user, attendance $attendance): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * 
     * @param User $user ユーザーモデル
     * @param attendance $attendance 勤怠モデル
     * @return bool
     */
    public function forceDelete(User $user, attendance $attendance): bool
    {
        return true;
    }
}
