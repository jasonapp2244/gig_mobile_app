<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DateTimeInterface;

class Task extends Model
{
    protected $fillable = [
    'user_id',
    'employer_id',
    'employer',
    'job_title',
    'position',
    'job_type',
    'job_category',
    'location',
    'supervisor',
    'supervisor_contact_number',
    'working_hours',
    'straight_time',
    'make_hole',
    'pay',
    'guaranteed_steady_hours',
    'flop_hours',
    'avg_hours',
    'bonus_pay',
    'travel_location',
    'travel_hours',
    'travel_pay',
    'st_wages',
    'st_hours',      // newly added
    'st_total',
    'wages',
    'ot_wages',
    'ot_total',
    'ot_start_time',
    'ot_end_time',
    'ot_hours',      // newly added
    'rate',
    'start_date',
    'end_date',
    'schedule_date',
    'start_time',
    'end_time',
    'task_date_time',
    'task_end_date_time',
    'is_reminder_sent',
    'reminder_sent_at',
    'status',
    'notes',
    'has_entry',
    'is_locked',
];

   protected $casts = [
    'pay' => 'decimal:2',
    'guaranteed_steady_hours' => 'decimal:2',
    'flop_hours' => 'decimal:2',
    'avg_hours' => 'decimal:2',
    'bonus_pay' => 'decimal:2',
    'travel_hours' => 'decimal:2',
    'travel_pay' => 'decimal:2',
    'st_wages' => 'decimal:2',
    'st_total' => 'decimal:2',
    'wages' => 'decimal:2',
    'ot_wages' => 'decimal:2',
    'ot_total' => 'decimal:2',
    'rate' => 'decimal:2',

    'start_date' => 'date',
    'end_date' => 'date',
    'schedule_date' => 'date',

    'start_time' => 'datetime:H:i:s',
    'end_time' => 'datetime:H:i:s',
    'ot_start_time' => 'datetime:H:i:s',
    'ot_end_time' => 'datetime:H:i:s',

    'st_hours' => 'string',  // newly added
    'ot_hours' => 'string',  // newly added

    'task_date_time' => 'datetime',
    'task_end_date_time' => 'datetime',

    'is_reminder_sent' => 'boolean',
    'reminder_sent_at' => 'datetime',

    'has_entry' => 'boolean',
    'is_locked' => 'boolean',
];


    // set for reminder
    public function getTaskDateTimeAttribute($value)
    {
        return $value
            ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
            : null;
    }

    public function getTaskEndDateTimeAttribute($value)
    {
        return $value
            ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
            : null;
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function taskPayments()
    {
        return $this->hasMany(TaskPayment::class);
    }

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function employerRelation()
    {
        return $this->belongsTo(User::class, 'employee_id', 'id');
    }

    public function employeeRelation()
    {
        return $this->belongsTo(User::class, 'employee_id', 'id');
    }

    // protected function serializeDate(\DateTimeInterface $date)
    // {
    //     return $date->timezone(config('app.timezone'))->toIso8601String();
    // }


    // public function serializeDate(\DateTimeInterface $date)
    // {
    //     return $date->setTimezone(new \DateTimeZone(config('app.timezone')))
    //                 ->format('Y-m-d H:i:s');
    // }

}
