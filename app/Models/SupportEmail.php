<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportEmail extends Model
{
    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'is_read',
        'sent_at',
        'response',
        'responded_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];
}
