<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'attendance_status',
        'admin_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function applications(): HasManyThrough
    {
        return $this->hasManyThrough(Application::class, Attendance::class);
    }

    public function attendanceStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 本日の勤怠情報を取得
                $attendance = $this->attendances()->whereDate('date', Carbon::now())->first();

                // なければ勤務外
                if (!$attendance) {
                    return '勤務外';
                } else {
                    // 退勤時刻が打刻されているなら退勤済み
                    if ($attendance->clock_out) {
                        return '退勤済';
                    } else {
                        // 出勤中の定義
                        // 1.休憩時間未登録なら出勤中
                        // 2.最新の休憩時間の休憩終了が打刻済み
                        // 上記以外なら休憩中とする
    
                        // 万が一同一時刻で打刻された場合の対策として最新のIDで判定とする
                        //$breaktime = $attendance->breaktimes()->latest()->first();
                        $breaktime = $attendance->breaktimes()
                            ->latest('id')
                            ->first();
                        if (!$breaktime || $breaktime->break_out) {
                            return '出勤中';
                        } else {
                            return '休憩中';
                        }
                    }
                }
            }
        );
    }
}
