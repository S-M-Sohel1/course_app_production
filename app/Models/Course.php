<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    protected $fillable = [
        'user_token',
        'name',
        'thumbnail',
        'video',
        'description',
        'type_id',
        'price',
        'lesson_num',
        'video_length',
        'follow',
        'score',
        'recommended'
    ];

    /**
     * Get the course type that owns the course.
     */
    public function courseType(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, 'type_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the teacher (member) that owns the course.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'user_token', 'token');
    }

    // Override toArray to replace paths with full URLs
    public function toArray()
    {
        $array = parent::toArray();
        
        // Replace thumbnail path with full URL
        if (isset($array['thumbnail'])) {
            $array['thumbnail'] = Storage::disk('s3')->url($array['thumbnail']);
        }
        
        // Replace video path with full URL
        if (isset($array['video'])) {
            $array['video'] = Storage::disk('s3')->url($array['video']);
        }
        
        return $array;
    }
}
