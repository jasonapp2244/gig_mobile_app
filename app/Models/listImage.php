<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class listImage extends Model
{
    protected $table = 'list_images';
    protected $fillable = ['list_id', 'image_name', 'path', 'hash_name', 'status'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['image_url'];

    public function list()
    {
        return $this->belongsTo(ListStory::class, 'list_id');
    }

    /**
     * Get the full URL for image
     */
    public function getImageUrlAttribute()
    {
        if (!$this->path) {
            return null;
        }
        return Storage::disk('public')->url($this->path);
    }
}
