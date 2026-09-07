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

    public function totaltime(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->clock_in || !$this->clock_out) {
                    return 0;
                }
                $minutes = (int) $this->clock_in->diffInMinutes($this->clock_out);
                return Attendance::minutestoHM($minutes);
            }
        );
    }

    public function totalBreakTime(): Attribute
    {
        return Attribute::make(
            get: function () {
                $breaks = $this->breaktimes;
                $break_time = 0;
                foreach ($breaks as $break) {
                    if ($break->break_in && $break->break_out) {
                        $break_time += $break->break_in->diffInMinutes($break->break_out);
                    }
                }
                return Attendance::minutestoHM($break_time);
            }
        );
    }

    public static function minutestoHM($minutes)
    {
        $h = (int) ((int) $minutes) / 60;
        $m = (int) ((int) $minutes) % 60;
        return sprintf('%d:%02d', $h, $m);
    }
}
