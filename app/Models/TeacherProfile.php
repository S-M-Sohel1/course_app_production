<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

class TeacherProfile extends Model
{
    use HasApiTokens;
    
    protected $fillable = ['user_token', 'avatar', 'cover', 'rating',
     'downloads', 'total_students', 'experience_years', 'job'];

    public function member()
    {
        return $this->belongsTo(Member::class, 'user_token', 'token');
    }

    // Override toArray to replace paths with full URLs
    public function toArray()
    {
        $array = parent::toArray();
        
        // Replace avatar path with full URL
        if (isset($array['avatar'])) {
            $array['avatar'] = Storage::disk('s3')->url($array['avatar']);
        }
        
        // Replace cover path with full URL
        if (isset($array['cover'])) {
            $array['cover'] = Storage::disk('s3')->url($array['cover']);
        }
        
        return $array;
    }
}
