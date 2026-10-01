<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * 勤怠レコードとのリレーション
     *
     * @return HasMany
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * ユーザーのもつ全ての勤怠修正申請レコードとのリレーション
     */
    public function applications(): HasManyThrough
    {
        return $this->hasManyThrough(Application::class, Attendance::class);
    }

    /**
     * 現在の勤怠状態を返す
     */
    public function attendanceStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 本日の勤怠レコード内容を判定し、現在の勤怠状態を返す
                $attendance = $this
                    ->attendances()
                    ->whereDate('date', Carbon::now())
                    ->first();

                if (! $attendance) {
                    return '勤務外';
                }

                if ($attendance->clock_out) {
                    return '退勤済';
                }

                // 出勤中の定義
                // 1.休憩時間未登録なら出勤中
                // 2.最新の休憩レコードの休憩終了が打刻済み
                // 上記以外なら休憩中とする
                $breaktime = $attendance
                    ->breaktimes()
                    ->latest('id')
                    ->first();

                if (! $breaktime || $breaktime->break_out) {
                    return '出勤中';
                } else {
                    return '休憩中';
                }
            }
        );
    }
}
