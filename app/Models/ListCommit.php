<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListCommit extends Model
{
    protected $table = 'list_commits';
    protected $fillable = [
        'user_id',
        'list_id',
        'commit',
        'status'
    ];

    public function list()
    {
        return $this->belongsTo(ListStory::class, 'list_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
