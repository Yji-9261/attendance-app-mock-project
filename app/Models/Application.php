<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'application_date',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'approval_status',
    ];

    protected $casts = [
        'application_date' => 'datetime',
        'new_clock_in' => 'datetime',
        'new_clock_out' => 'datetime',
    ];

    /**
     * 休憩時間修正申請レコードとのリレーション
     *
     * @return HasMany
     */
    public function breakapplications()
    {
        return $this->hasMany(BreakApplication::class);
    }

    /**
     * 勤怠レコードとのリレーション
     *
     * @return BelongsTo
     */
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
