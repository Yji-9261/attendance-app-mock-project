<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'date' => 'datetime',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    /**
     * 勤怠修正申請レコードとのリレーション
     *
     * @return HasMany
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 休憩時間レコードとのリレーション
     *
     * @return HasMany
     */
    public function breaktimes()
    {
        return $this->hasMany(BreakTime::class);
    }

    /**
     * ユーザーレコードとのリレーション
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 休憩時間を除いた実勤務時間をH:i形式の文字列で返す
     */
    public function totalTime(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->minutestoHM($this->calculateWorkMinutes());
            }
        );
    }

    /**
     * 休憩時間をH:i形式の文字列で返す
     */
    public function totalBreakTime(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->minutestoHM($this->calculateBreakMinutes());
            }
        );
    }

    /**
     * 休憩時間を除いた実勤務時間を分で返す
     * 
     * @return int 実勤務時間
     */
    public function calculateWorkMinutes(): int
    {
        // 休憩終了時間が打刻前は休憩時間0として算出
        if (!$this->clock_out) {
            return 0;
        }
        $minutes = (int) $this->clock_in->diffInMinutes($this->clock_out);

        return $minutes - $this->calculateBreakMinutes();
    }

    /**
     * 休憩時間合計を分で返す
     * 
     * @return int 休憩時間合計
     */
    public function calculateBreakMinutes(): int
    {
        return $this->breaktimes->sum(function ($breakTime) {
            // 休憩終了が打刻前なら０として計算
            if (!$breakTime->break_out) {
                return 0;
            }

            return (int) $breakTime->break_in->diffInMinutes($breakTime->break_out);
        });
    }

    /**
     * 分から「時:分」形式に変換する
     * 
     * @param int $minutes 分
     * @return string H:i形式の時間文字列
     */
    private function minutestoHM(int $minutes): string
    {
        $h = (int) ((int) $minutes) / 60;
        $m = (int) ((int) $minutes) % 60;

        return sprintf('%d:%02d', $h, $m);
    }
}
