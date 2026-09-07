<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
//use \App\Models\Attendance;
//use \App\Models\BreakTime;

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

    public function breakapplications()
    {
        return $this->hasMany(BreakApplication::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
