<?php

namespace App\Models;

use Filament\Http\Middleware\Authenticate;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Member extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;
    
    protected $fillable = ['name', 'email', 'age', 'avatar', 'role', 'password',
     'open_id', 'access_token', 'token',];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    protected $casts = [
        'password' => 'hashed',
    ];

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class, 'user_token', 'token');
    }
    
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'teacher') {
            return $this->role === 'teacher';
        }

        return false;
    }
    
    // Check if this member is a teacher
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    // Check if this member is a student
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    // Override toArray to replace avatar path with full URL
    public function toArray()
    {
        $array = parent::toArray();
        
        // Replace avatar path with full URL
        if (isset($array['avatar'])) {
            $array['avatar'] = Storage::disk('s3')->url($array['avatar']);
        }
        
        return $array;
    }
}

