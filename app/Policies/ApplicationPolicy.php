<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Perform pre-authorization checks
     * 
     * @param User $user ユーザーモデル
     * @param string $ability アクション
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
     * @param User $user ユーザーモデル
     * @return bool 常にtrue
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * 
     * @param User $user ユーザーモデル
     * @param Application $application 勤怠修正申請モデル
     * @return bool 本人の勤怠修正申請ならtrue、それ以外はfalse
     */
    public function view(User $user, Application $application): bool
    {
        return $user->id === $application->attendance->user_id;
    }

    /**
     * Determine whether the user can create models.
     * 
     * @param User $user ユーザーモデル
     * @return bool 常にtrue
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * 
     * @param User $user ユーザーモデル
     * @param Application $application 勤怠修正申請モデル
     * @return bool 常にtrue
     */
    public function update(User $user, Application $application): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * @param User $user ユーザーモデル
     * @param Application $application 勤怠修正申請モデル
     * @return bool 常にtrue
     */
    public function delete(User $user, Application $application): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     * 
     * @param User $user ユーザーモデル
     * @param Application $application 勤怠修正申請モデル
     * @return bool 常にtrue
     */
    public function restore(User $user, Application $application): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * 
     * @param User $user ユーザーモデル
     * @param Application $application 勤怠修正申請モデル
     * @return bool 常にtrue
     */
    public function forceDelete(User $user, Application $application): bool
    {
        return true;
    }
}
