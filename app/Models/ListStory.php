<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListStory extends Model
{
    protected $table = 'list_stories';
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'old_price',
        'new_price',
        'location',
        'description',
        'condition',
        'status'
    ];


public function images()
{
    return $this->hasMany(listImage::class, 'list_id');
}


    public function category()
    {
        return $this->belongsTo(ListCategory::class, 'category_id');
    }

    public function comments()
    {
        return $this->hasMany(ListCommit::class, 'list_id');
    }

    public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}
}
