<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Employer extends Model
{
    protected $fillable = ['user_id', 'employer_name', 'job_type', 'salary', 'location', 'description', 'employer_image', 'status'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['employer_image_url'];


    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the full URL for employer image
     */
    public function getEmployerImageUrlAttribute()
    {
        if (!$this->employer_image) {
            return null;
        }
        return Storage::disk('public')->url('employer_images/' . $this->employer_image);
    }
}
