<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListReview extends Model
{
    protected $table = 'list_reviews';
    protected $fillable = [
        'user_id',
        'list_id',
        'review',
        'rating',
        'status',
        'is_anonymous',
        'is_verified'
    ];

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lists()
    {
        return $this->belongsTo(ListStory::class, 'list_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function list()
    {
        return $this->belongsTo(ListStory::class, 'list_id');
    }
}
