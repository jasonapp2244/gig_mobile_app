<?php

namespace App\Models;

use App\Services\ListImageThumbnail;
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
    protected $appends = ['image_url', 'thumb_url'];

    protected static function booted(): void
    {
        // Small copy for list screens; the original photo is untouched
        static::created(fn (listImage $image) => ListImageThumbnail::make($image->path));
        static::deleted(fn (listImage $image) => ListImageThumbnail::delete($image->path));
    }

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

    /**
     * Small (max 480 px) JPEG for list screens; the full image_url when no thumbnail exists yet.
     */
    public function getThumbUrlAttribute()
    {
        $thumb = ListImageThumbnail::pathFor($this->path);

        if ($thumb && Storage::disk('public')->exists($thumb)) {
            return Storage::disk('public')->url($thumb);
        }

        return $this->image_url;
    }
}
