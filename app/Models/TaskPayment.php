<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskPayment extends Model
{
    protected $fillable = [
    'user_id',
    'task_id',
    'payment_title',
    'payment',
    'note',
    'create_date',
    'payment_status',
];



public function user()
{
    return $this->belongsTo(User::class);
}

public function task()
{
    return $this->belongsTo(Task::class);
}

}
