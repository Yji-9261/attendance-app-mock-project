<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
    ];

    protected $casts = [
        'date' => 'datetime',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function breaktimes()
    {
        return $this->hasMany(BreakTime::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 休憩を除いた勤務時間
     * @return Attribute
     */
    public function totalTime(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 出退勤が入力されていないなら0とする(想定上は退勤時間が未入力はあり得る)
                if (!$this->clock_in || !$this->clock_out) {
                    return 0;
                }
                $minutes = (int) $this->clock_in->diffInMinutes($this->clock_out);
                // 休憩時間を抜く仕様
                $minutes -= $this->calculateBreakTimeMinutes();
                return $this->minutestoHM($minutes);
            }
        );
    }

    /**
     * 休憩合計時間
     * @return Attribute
     */
    public function totalBreakTime(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->minutestoHM($this->calculateBreakTimeMinutes());
            }
        );
    }

    /**
     * 休憩時間の総時間を分にして返す
     */
    private function calculateBreakTimeMinutes()
    {
        $breaks = $this->breaktimes;
        $break_time = 0;
        foreach ($breaks as $break) {
            if ($break->break_in && $break->break_out) {
                $break_time += $break->break_in->diffInMinutes($break->break_out);
            }
        }
        return $break_time;
    }

    /**
     * 総分数から時:分に変換する
     * @param mixed $minutes
     * @return string
     */
    private function minutestoHM($minutes)
    {
        $h = (int) ((int) $minutes) / 60;
        $m = (int) ((int) $minutes) % 60;
        return sprintf('%d:%02d', $h, $m);
    }
}
